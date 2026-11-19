<?php

use App\Models\User;

test('authenticated users can update their profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/user/profile-information', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertSuccessful();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('profile update validation rejects invalid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/user/profile-information', [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

test('email verification status is unchanged when email is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/user/profile-information', [
        'name' => 'Same Email User',
        'email' => $user->email,
    ])->assertSuccessful();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('guests cannot update profile information', function () {
    $this->putJson('/api/v1/user/profile-information', [
        'name' => 'Guest',
        'email' => 'guest@example.com',
    ])->assertUnauthorized();
});
