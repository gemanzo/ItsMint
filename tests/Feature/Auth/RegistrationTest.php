<?php

use App\Models\Invitation;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register with a valid invitation code', function () {
    $invitation = Invitation::createAlphaInvite();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'inviteCode' => $invitation->code,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($invitation->fresh()->used_count)->toBe(1);
});

test('registration requires an invitation code while alpha mode is enabled', function () {
    config(['app.alpha_mode' => true]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('inviteCode');
    $this->assertGuest();
});

test('without a valid invitation the response does not reveal registered emails', function () {
    config(['app.alpha_mode' => true]);
    $existingUser = User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'name' => 'Curious Visitor',
        'email' => $existingUser->email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'inviteCode' => 'NOTVALID',
    ]);

    $response->assertSessionHasErrors('inviteCode');
    $response->assertSessionDoesntHaveErrors('email');
});

test('registration attempts are rate limited', function () {
    $registrationAttempt = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'inviteCode' => 'NOTVALID',
    ];

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $this->post('/register', $registrationAttempt)->assertRedirect();
    }

    $this->post('/register', $registrationAttempt)->assertTooManyRequests();
});
