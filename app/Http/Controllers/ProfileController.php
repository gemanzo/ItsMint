<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
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

        try {
            $user = $request->user();
            $validated = $request->validated();

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
            }

            // Remove the profile_photo from validated data as it's not a model attribute
            unset($validated['profile_photo']);
            unset($validated['remove_photo']);

            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

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

        // Stop billing BEFORE deleting anything: if a subscription can't be cancelled
        // (e.g. Stripe unreachable) the account must stay, otherwise the user would keep
        // being charged without any way to manage the subscription.
        try {
            $this->cancelActiveSubscriptions($user);
        } catch (\Throwable $exception) {
            Log::error('Account deletion aborted: subscription cancellation failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'account' => 'We could not cancel your subscription, so your account was not deleted. Please try again later or contact support.',
            ]);
        }

        // Delete profile photo before deleting user
        if ($user->profile_photo_path) {
            $this->deleteProfilePhoto($user);
        }

        $this->deleteCardScanImages($user);

        Auth::logout();

        DB::transaction(function () use ($user) {
            // Cashier's tables have no foreign key to users: remove the rows explicitly
            foreach ($user->subscriptions as $subscription) {
                $subscription->items()->delete();
                $subscription->delete();
            }

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Cancel immediately every subscription that is still running on Stripe.
     */
    private function cancelActiveSubscriptions(User $user): void
    {
        foreach ($user->subscriptions as $subscription) {
            if (! $subscription->ended()) {
                $subscription->cancelNow();
            }
        }
    }

    /**
     * Delete the user's card scan images (stored under card-scans/{user id}/).
     *
     * Best effort: a storage failure must not block the account deletion, but it is
     * logged because those images would remain stored.
     */
    private function deleteCardScanImages(User $user): void
    {
        try {
            Storage::disk('s3-card-scans')->deleteDirectory('card-scans/'.$user->id);
        } catch (\Throwable $exception) {
            Log::error('Could not delete card scan images of a deleted account', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
