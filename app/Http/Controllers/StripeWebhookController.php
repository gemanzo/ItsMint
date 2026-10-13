<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhooks: Cashier keeps the local subscriptions table in sync,
 * here we only add safety checks and logging.
 *
 * Premium features are resolved on every request from the subscription state
 * (Pennant "array" store), so no feature cache needs to be invalidated here.
 */
final class StripeWebhookController extends CashierController
{
    /**
     * Handle a Stripe webhook call.
     *
     * Cashier verifies the Stripe signature only when a webhook secret is configured:
     * without it anyone could forge events (e.g. a fake "subscription created"),
     * so in that case we refuse to process the event at all.
     */
    public function handleWebhook(Request $request): Response
    {
        if (empty(config('cashier.webhook.secret'))) {
            Log::critical('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured: event rejected');

            return new Response('Webhook secret not configured', 500);
        }

        return parent::handleWebhook($request);
    }

    /**
     * Handle customer subscription created.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $subscription = $user->subscriptions()->where('stripe_id', $payload['data']['object']['id'])->first();

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
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $subscription = $user->subscriptions()->where('stripe_id', $payload['data']['object']['id'])->first();

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
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        // Let Cashier handle the subscription sync first
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            Log::info('Subscription deleted', [
                'user_id' => $user->id,
                'stripe_subscription_id' => $payload['data']['object']['id'],
                'has_premium' => $user->subscribed('premium'),
            ]);
        }

        return $response;
    }

    /**
     * Handle invoice payment succeeded.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): Response
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
        }

        return $response;
    }

    /**
     * Handle invoice payment failed.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
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

            // TODO: Send notification to user about payment failure
            // This could be implemented with Laravel Notifications
        }

        return $response;
    }
}
