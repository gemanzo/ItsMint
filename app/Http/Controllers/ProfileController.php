<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        Log::info('Profile edit method called');

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        Log::info('Profile update method called', [
            'request_method' => $request->method(),
            'all_data' => $request->all(),
            'hasFile_profile_photo' => $request->hasFile('profile_photo'),
            'file_details' => $request->hasFile('profile_photo') ? [
                'name' => $request->file('profile_photo')->getClientOriginalName(),
                'size' => $request->file('profile_photo')->getSize(),
                'mime' => $request->file('profile_photo')->getMimeType(),
            ] : null,
            'remove_photo' => $request->boolean('remove_photo'),
        ]);

        // Force flush logs
        Log::info('BEFORE VALIDATION - USER DATA', [
            'user_id' => $request->user()->id,
            'current_profile_photo_path' => $request->user()->profile_photo_path,
        ]);

        try {
            $user = $request->user();
            $validated = $request->validated();

            // Debug logging
            Log::info('Profile update request', [
                'has_file' => $request->hasFile('profile_photo'),
                'remove_photo' => $request->boolean('remove_photo'),
                'user_id' => $user->id,
            ]);

            // Handle profile photo removal
            if ($request->boolean('remove_photo') && $user->profile_photo_path) {
                $this->deleteProfilePhoto($user);
                $validated['profile_photo_path'] = null;
            }

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                // Delete old photo if exists
                if ($user->profile_photo_path) {
                    $this->deleteProfilePhoto($user);
                }

                // Store new photo
                $path = $request->file('profile_photo')->store('profile-photos', 'public');
                $validated['profile_photo_path'] = $path;

                \Log::info('Photo uploaded', ['path' => $path]);
            }

            // Remove the profile_photo from validated data as it's not a model attribute
            unset($validated['profile_photo']);
            unset($validated['remove_photo']);

            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            \Log::info('User saved', ['profile_photo_path' => $user->profile_photo_path]);

            return Redirect::route('profile.edit')->with('status', 'profile-updated');
        } catch (\Exception $e) {
            // Handle any exceptions that might occur during the update process
            Log::error('Profile update error', ['exception' => $e]);

            return Redirect::route('profile.edit')->with('error', 'An error occurred while updating your profile.');
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Delete profile photo before deleting user
        if ($user->profile_photo_path) {
            $this->deleteProfilePhoto($user);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Delete the user's profile photo from storage.
     */
    private function deleteProfilePhoto($user): void
    {
        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }
}
