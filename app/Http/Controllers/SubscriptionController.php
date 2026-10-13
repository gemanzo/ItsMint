<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SubscriptionController extends Controller
{
    public function checkout(Request $request)
    {
        $user = $request->user();

        // Never open a second checkout for a user who is already premium:
        // it would create a duplicate subscription billed twice
        if ($user->subscribed('premium')) {
            $alreadySubscribedMessage = 'You already have an active Premium subscription.';

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['error' => $alreadySubscribedMessage], 409);
            }

            return redirect()->route('subscription.index')->with('error', $alreadySubscribedMessage);
        }

        // Create or get Stripe customer
        if (! $user->hasStripeId()) {
            $user->createOrGetStripeCustomer();
        }

        // Create a Checkout Session for the single premium price
        $priceId = (string) config('services.stripe.price_id_premium');
        
        if (! $priceId) {
            Log::error('STRIPE_PRICE_ID_PREMIUM not configured');
            return response()->json([
                'error' => 'Checkout configuration error. Please contact support.',
            ], 500);
        }

        try {
            // newSubscription() puts the subscription type into subscription_data.metadata:
            // that's what Cashier reads in the webhook to create a "premium" subscription.
            // (Metadata on the Checkout Session itself never reaches the subscription.)
            $checkout = $user->newSubscription('premium', $priceId)->checkout([
                'success_url' => url('/subscribe?success=1'),
                'cancel_url' => url('/subscribe?canceled=1'),
            ]);

            // Cashier returns a Checkout object with url property
            // For API requests (like Inertia SPA), return JSON with URL
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'url' => $checkout->url,
                ]);
            }

            // For regular requests, redirect directly
            return redirect($checkout->url);
        } catch (\Exception $e) {
            Log::error('Stripe checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create checkout session. Please try again.',
            ], 500);
        }
    }

    public function success(Request $request)
    {
        // Premium features are resolved on every request from the subscription state,
        // so there is no feature cache to invalidate here.

        // Redirect to subscription page with success message
        return redirect()->route('subscription.index', ['success' => '1']);
    }

    public function canceled(Request $request)
    {
        // Redirect to subscription page with canceled message
        return redirect()->route('subscription.index', ['canceled' => '1']);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscriptions()
            ->where('type', 'premium')
            ->where('stripe_status', 'active')
            ->first();

        return response()->json([
            'subscribed' => $user->subscribed('premium'),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function portal(Request $request)
    {
        $user = $request->user();
        
        if (! $user->hasStripeId()) {
            return redirect()->route('subscription.index')
                ->with('error', 'No billing account found. Please subscribe first.');
        }

        try {
            $url = $user->billingPortalUrl(route('subscription.index'));
            return redirect()->away($url);
        } catch (\Exception $e) {
            Log::error('Failed to create billing portal URL', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('subscription.index')
                ->with('error', 'Failed to access billing portal. Please try again.');
        }
    }
}
