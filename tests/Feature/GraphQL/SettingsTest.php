<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Features;

test('GraphQL preferences reads and updates only the current user', function () {
    $other = User::factory()->create(['timezone' => 'UTC']);
    $user = User::factory()->create(['timezone' => 'Australia/Sydney']);

    $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ myPreferences { timezone } }',
    ])->assertExactJson(['data' => ['myPreferences' => ['timezone' => 'Australia/Sydney']]]);

    $this->postJson('/graphql', [
        'query' => 'mutation ($input: UpdateMyPreferencesInput!) { updateMyPreferences(input: $input) { timezone } }',
        'variables' => ['input' => ['timezone' => 'Europe/Paris']],
    ])->assertExactJson(['data' => ['updateMyPreferences' => ['timezone' => 'Europe/Paris']]]);

    expect($user->fresh()->timezone)->toBe('Europe/Paris')
        ->and($other->fresh()->timezone)->toBe('UTC');
});

test('GraphQL preference validation leaves the database unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation { updateMyPreferences(input: { timezone: "invalid" }) { timezone } }',
    ])->assertJsonStructure(['errors' => [['extensions' => ['validation' => ['timezone']]]]]);

    expect($user->fresh()->timezone)->toBe('UTC');
});

test('GraphQL settings operations reject guests', function (string $query) {
    $this->postJson('/graphql', ['query' => $query])
        ->assertOk()->assertJsonPath('errors.0.extensions.code', 'UNAUTHENTICATED');
})->with([
    '{ myPreferences { timezone } }',
    '{ mySecuritySettings { twoFactorEnabled } }',
    'mutation { updateMyPreferences(input: { timezone: "UTC" }) { timezone } }',
    'mutation { updateMyProfile(input: { name: "Guest", email: "guest@example.com" }) { id } }',
    'mutation { updateMyPassword(input: { current_password: "password", password: "new-password", password_confirmation: "new-password" }) }',
    'mutation { deleteMyAccount(input: { password: "password" }) }',
]);

test('GraphQL verified settings reject unverified users', function (string $query) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/graphql', ['query' => $query])
        ->assertOk()->assertJsonPath('errors.0.extensions.code', 'FORBIDDEN');

    expect($user->fresh()->timezone)->toBe('UTC');
})->with([
    '{ myPreferences { timezone } }',
    '{ mySecuritySettings { twoFactorEnabled } }',
    'mutation { updateMyPreferences(input: { timezone: "Europe/Paris" }) { timezone } }',
]);

test('GraphQL security settings expose state without two-factor secrets', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ mySecuritySettings { canManageTwoFactor twoFactorEnabled requiresConfirmation passwordRules } }',
    ])->assertJsonPath('data.mySecuritySettings.canManageTwoFactor', true)
        ->assertJsonPath('data.mySecuritySettings.twoFactorEnabled', true)
        ->assertJsonPath('data.mySecuritySettings.requiresConfirmation', true)
        ->assertJsonStructure(['data' => ['mySecuritySettings' => ['passwordRules']]])
        ->assertJsonMissingPath('data.mySecuritySettings.two_factor_secret');
});

test('GraphQL profile updates reset verification only when email changes', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $query = 'mutation ($input: UpdateMyProfileInput!) { updateMyProfile(input: $input) { id name email emailVerifiedAt } }';

    $this->actingAs($user)->postJson('/graphql', [
        'query' => $query,
        'variables' => ['input' => ['name' => 'Updated', 'email' => $user->email]],
    ])->assertJsonPath('data.updateMyProfile.name', 'Updated');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();

    $this->postJson('/graphql', [
        'query' => $query,
        'variables' => ['input' => ['name' => 'Updated', 'email' => 'new@example.com']],
    ])->assertJsonPath('data.updateMyProfile.email', 'new@example.com')
        ->assertJsonPath('data.updateMyProfile.emailVerifiedAt', null);
    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and($other->fresh()->email)->toBe($other->email);
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('GraphQL profile updates reject invalid or duplicate email', function (string $email) {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation ($input: UpdateMyProfileInput!) { updateMyProfile(input: $input) { id } }',
        'variables' => ['input' => ['name' => 'Changed', 'email' => $email]],
    ])->assertJsonStructure(['errors' => [['extensions' => ['validation' => ['email']]]]]);

    expect($user->fresh()->name)->toBe($user->name)
        ->and($user->fresh()->email)->toBe($user->email);
})->with(['not-an-email', 'taken@example.com']);

test('GraphQL password updates reuse current password and confirmation rules', function () {
    $user = User::factory()->unverified()->create(['password' => 'password']);
    $query = 'mutation ($input: UpdateMyPasswordInput!) { updateMyPassword(input: $input) }';

    foreach ([['wrong', 'new-password', 'new-password', 'current_password'], ['password', 'new-password', 'mismatch', 'password']] as [$current, $password, $confirmation, $field]) {
        $this->actingAs($user)->postJson('/graphql', [
            'query' => $query,
            'variables' => ['input' => ['current_password' => $current, 'password' => $password, 'password_confirmation' => $confirmation]],
        ])->assertJsonStructure(['errors' => [['extensions' => ['validation' => [$field]]]]]);
        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    }

    $this->postJson('/graphql', [
        'query' => $query,
        'variables' => ['input' => ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password']],
    ])->assertExactJson(['data' => ['updateMyPassword' => true]]);
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('GraphQL account deletion requires the current password and logs out', function () {
    config(['sanctum.stateful' => ['localhost']]);
    $this->withHeader('Origin', 'http://localhost');
    $other = User::factory()->create();
    $user = User::factory()->unverified()->create(['password' => 'password']);

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation { deleteMyAccount(input: { password: "wrong" }) }',
    ])->assertJsonStructure(['errors' => [['extensions' => ['validation' => ['password']]]]]);
    expect($user->fresh())->not->toBeNull();

    $this->withSession(['marker' => 'present'])->postJson('/graphql', [
        'query' => 'mutation { deleteMyAccount(input: { password: "password" }) }',
    ])->assertExactJson(['data' => ['deleteMyAccount' => true]])->assertSessionMissing('marker');
    $this->assertGuest('web');
    expect($user->fresh())->toBeNull()->and($other->fresh())->not->toBeNull();
});

test('GraphQL respects disabled Fortify profile and password features', function (string $query) {
    config(['fortify.features' => [Features::emailVerification()]]);
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user)->postJson('/graphql', ['query' => $query])
        ->assertJsonPath('errors.0.extensions.code', 'FORBIDDEN');
    expect($user->fresh()->name)->toBe($user->name)
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'mutation { updateMyProfile(input: { name: "Changed", email: "changed@example.com" }) { id } }',
    'mutation { updateMyPassword(input: { current_password: "password", password: "new-password", password_confirmation: "new-password" }) }',
]);

test('GraphQL password changes preserve leading and trailing whitespace', function () {
    $user = User::factory()->create(['password' => ' current-password ']);

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation ($input: UpdateMyPasswordInput!) { updateMyPassword(input: $input) }',
        'variables' => ['input' => [
            'current_password' => ' current-password ',
            'password' => ' new-password ',
            'password_confirmation' => ' new-password ',
        ]],
    ])->assertExactJson(['data' => ['updateMyPassword' => true]]);

    expect(Hash::check(' new-password ', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('new-password', $user->fresh()->password))->toBeFalse();
});

test('GraphQL deletion accepts the exact password including whitespace', function () {
    config(['sanctum.stateful' => ['localhost']]);
    $user = User::factory()->create(['password' => ' current-password ']);

    $this->withHeader('Origin', 'http://localhost')->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation ($input: DeleteMyAccountInput!) { deleteMyAccount(input: $input) }',
        'variables' => ['input' => ['password' => ' current-password ']],
    ])->assertExactJson(['data' => ['deleteMyAccount' => true]]);

    expect($user->fresh())->toBeNull();
    $this->assertGuest('web');
});

test('GraphQL password changes revoke reset tokens and dispatch the Fortify event', function () {
    Event::fake([PasswordUpdatedViaController::class]);
    $user = User::factory()->create(['password' => 'password']);
    $broker = Password::broker(config('fortify.passwords'));
    $token = $broker->createToken($user);
    expect($broker->tokenExists($user, $token))->toBeTrue();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation { updateMyPassword(input: { current_password: "password", password: "new-password", password_confirmation: "new-password" }) }',
    ])->assertExactJson(['data' => ['updateMyPassword' => true]]);

    expect($broker->tokenExists($user, $token))->toBeFalse();
    Event::assertDispatched(PasswordUpdatedViaController::class);
});

test('GraphQL normalizes non-secret profile fields before validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation { updateMyProfile(input: { name: "  Updated  ", email: "  normalized@example.com  " }) { name email } }',
    ])->assertJsonPath('data.updateMyProfile.name', 'Updated')
        ->assertJsonPath('data.updateMyProfile.email', 'normalized@example.com');

    expect($user->fresh()->name)->toBe('Updated')->and($user->fresh()->email)->toBe('normalized@example.com');
});

test('GraphQL security state respects disabled two-factor configuration', function () {
    config(['fortify.features' => [Features::emailVerification()]]);
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ mySecuritySettings { canManageTwoFactor twoFactorEnabled requiresConfirmation } }',
    ])->assertExactJson(['data' => ['mySecuritySettings' => [
        'canManageTwoFactor' => false,
        'twoFactorEnabled' => false,
        'requiresConfirmation' => false,
    ]]]);
});

test('stateful GraphQL requests enforce CSRF protection', function () {
    config(['sanctum.stateful' => ['localhost']]);
    $this->app->instance('env', 'local');
    $user = User::factory()->create(['timezone' => 'UTC']);
    $query = ['query' => '{ myPreferences { timezone } }'];

    $this->actingAs($user)->withHeader('Origin', 'http://localhost')
        ->postJson('/graphql', $query)->assertStatus(419);

    $this->withSession(['_token' => 'test-csrf-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
        ->postJson('/graphql', $query)
        ->assertExactJson(['data' => ['myPreferences' => ['timezone' => 'UTC']]]);
});

test('GraphQL cannot reuse the deleted user in a subsequent mutation field', function () {
    config(['sanctum.stateful' => ['localhost']]);
    $user = User::factory()->create(['password' => 'password']);

    $this->withHeader('Origin', 'http://localhost')->actingAs($user)->postJson('/graphql', [
        'query' => 'mutation { deleteMyAccount(input: { password: "password" }) updateMyProfile(input: { name: "Restored", email: "restored@example.com" }) { id } }',
    ])->assertJsonPath('errors.0.extensions.code', 'UNAUTHENTICATED');

    expect($user->fresh())->toBeNull();
});
