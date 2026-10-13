<?php

use App\Models\User;

test('api routes are rate limited per user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '120');
});
