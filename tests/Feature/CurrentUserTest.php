<?php

use App\Models\User;

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
