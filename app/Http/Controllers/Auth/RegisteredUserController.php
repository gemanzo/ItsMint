<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $inviteCode = $request->query('invite');

        return Inertia::render('Auth/Register', [
            'invite_code' => $inviteCode,
            'requires_invite' => config('app.alpha_mode', true), // You can toggle this in config
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Validate basic user data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Check if alpha mode is enabled
        if (config('app.alpha_mode', true)) {
            $request->validate([
                'invite_code' => 'required|string',
            ]);

            $invitation = $this->validateInvitationCode($request->invite_code, $request->email);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Mark invitation as used if in alpha mode
        if (isset($invitation)) {
            $invitation->markAsUsed($user);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Validate invitation code
     */
    private function validateInvitationCode(string $code, string $email): Invitation
    {
        $invitation = Invitation::byCode($code)->usable()->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invite_code' => 'Code not valid or expired.',
            ]);
        }

        if (! $invitation->canBeUsedBy($email)) {
            throw ValidationException::withMessages([
                'invite_code' => 'This invitation code cannot be used with this email.',
            ]);
        }

        return $invitation;
    }
}
