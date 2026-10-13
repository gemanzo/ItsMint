<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

// Card scans (Google Vision OCR, billed per call) are restricted to admins
// through the "role" middleware alias registered in bootstrap/app.php.

test('guests cannot access card scans', function () {
    $this->getJson('/api/card-scans')->assertUnauthorized();
});

test('non admin users are forbidden from card scans', function () {
    Role::create(['name' => 'admin']);
    $regularUser = User::factory()->create();

    // Before the alias fix this was a 500 (middleware class not found) for everyone
    $this->actingAs($regularUser)->getJson('/api/card-scans')->assertForbidden();
});
