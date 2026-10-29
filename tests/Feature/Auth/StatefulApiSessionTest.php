<?php

use App\Models\User;

/**
 * The SPA calls the API with the web session (Sanctum stateful requests). Logging in
 * through the form stores a hash of the password in the session, which Sanctum
 * verifies on every API call: if the two disagree, the user is logged out.
 * actingAs() doesn't store that hash, so this test logs in for real.
 */
test('API calls from the frontend keep the user logged in', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    // A request coming from the frontend (stateful domain) uses the web session
    $this->withHeader('Referer', config('app.url').'/dashboard')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);

    $this->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($user);
});
