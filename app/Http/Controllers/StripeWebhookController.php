<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Laravel\Pennant\Feature;

final class StripeWebhookController extends CashierController
{
    /**
     * Handle customer subscription created.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $subscription = $user->subscriptions()->where('stripe_id', $payload['data']['object']['id'])->first();

            if ($subscription && $subscription->type === 'premium') {
                // Invalidate feature cache to ensure features are updated
                Feature::for($user)->flush();
            }

            Log::info('Subscription created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'stripe_subscription_id' => $payload['data']['object']['id'],
                'type' => $subscription?->type,
            ]);
        }

        return $response;
    }

    /**
     * Handle customer subscription updated.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $subscription = $user->subscriptions()->where('stripe_id', $payload['data']['object']['id'])->first();

            if ($subscription && $subscription->type === 'premium') {
                // Invalidate feature cache to ensure features are updated
                Feature::for($user)->flush();
            }

            Log::info('Subscription updated', [
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'stripe_subscription_id' => $payload['data']['object']['id'],
                'status' => $payload['data']['object']['status'],
                'type' => $subscription?->type,
            ]);
        }

        return $response;
    }

    /**
     * Handle customer subscription deleted.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            // After deletion, subscription might be gone, so check all premium subscriptions
            $hasPremium = $user->subscribed('premium');

            if (! $hasPremium) {
                // Invalidate feature cache to remove premium features
                Feature::for($user)->flush();
            }

            Log::info('Subscription deleted', [
                'user_id' => $user->id,
                'stripe_subscription_id' => $payload['data']['object']['id'],
                'has_premium' => $hasPremium,
            ]);
        }

        return $response;
    }

    /**
     * Handle invoice payment succeeded.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        // Let Cashier handle the invoice sync first
        $response = parent::handleInvoicePaymentSucceeded($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            Log::info('Invoice payment succeeded', [
                'user_id' => $user->id,
                'invoice_id' => $payload['data']['object']['id'],
                'amount' => $payload['data']['object']['amount_paid'],
            ]);

            // Invalidate feature cache to ensure subscription status is fresh
            Feature::for($user)->flush();
        }

        return $response;
    }

    /**
     * Handle invoice payment failed.
     */
    protected function handleInvoicePaymentFailed(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        // Let Cashier handle the invoice sync first
        $response = parent::handleInvoicePaymentFailed($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            Log::warning('Invoice payment failed', [
                'user_id' => $user->id,
                'invoice_id' => $payload['data']['object']['id'],
                'amount' => $payload['data']['object']['amount_due'],
            ]);

            // Invalidate feature cache - subscription may be past_due
            Feature::for($user)->flush();

            // TODO: Send notification to user about payment failure
            // This could be implemented with Laravel Notifications
        }

        return $response;
    }
}
