<?php

use App\Models\User;

test('unauthenticated users cannot access the current user endpoint', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertUnauthorized();
});

test('authenticated users receive a typed current user resource', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/user');

    $response->assertOk();
    $response->assertJsonPath('data.id', $user->id);
    $response->assertJsonPath('data.name', 'Jane Doe');
    $response->assertJsonPath('data.email', 'jane@example.com');
    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'email',
            'email_verified_at',
        ],
    ]);
});

test('current user resource does not expose sensitive attributes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/user');

    $response->assertOk();
    $response->assertJsonMissingPath('data.password');
    $response->assertJsonMissingPath('data.remember_token');
    $response->assertJsonMissingPath('data.two_factor_secret');
    $response->assertJsonMissingPath('data.two_factor_recovery_codes');
    expect($response->json('data'))->not->toHaveKey('password');
    expect($response->json())->not->toHaveKey('password');
});

test('GraphQL me returns null for guests', function () {
    $this->postJson('/graphql', [
        'query' => '{ me { id name email email_verified_at: emailVerifiedAt } }',
    ])->assertOk()->assertExactJson(['data' => ['me' => null]]);
});

test('GraphQL me returns only the authenticated user profile', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ me { id name email email_verified_at: emailVerifiedAt } }',
    ]);

    $response->assertOk()->assertExactJson([
        'data' => [
            'me' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at->format('Y-m-d H:i:s'),
            ],
        ],
    ]);
    expect($user->fresh()->email)->toBe($user->email)
        ->and($other->fresh()->email)->toBe($other->email);
});

test('GraphQL me preserves an unverified email status', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ me { id emailVerifiedAt } }',
    ])->assertOk()->assertExactJson([
        'data' => ['me' => ['id' => (string) $user->id, 'emailVerifiedAt' => null]],
    ]);
});

test('GraphQL does not allow selecting user secrets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/graphql', [
        'query' => '{ me { password two_factor_secret } }',
    ])->assertOk()->assertJsonStructure(['errors'])->assertJsonMissingPath('data.me');
});
