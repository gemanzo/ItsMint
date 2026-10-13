<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Laravel\Pennant\Feature;

const TEST_STRIPE_WEBHOOK_SECRET = 'whsec_test_secret';

/**
 * CSRF middleware that behaves as in production: Laravel skips CSRF checks
 * while running tests, which would hide a missing exclusion.
 */
function productionLikeCsrfMiddleware(): ValidateCsrfToken
{
    return new class(app(), app('encrypter')) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
}

/**
 * Payload of a "customer.subscription.created" event for the given customer.
 */
function subscriptionCreatedEventPayload(string $stripeCustomerId, string $subscriptionType): array
{
    return [
        'id' => 'evt_test_subscription_created',
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_test_123',
                'customer' => $stripeCustomerId,
                'status' => 'active',
                'metadata' => [
                    'type' => $subscriptionType,
                    'name' => $subscriptionType,
                ],
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_test_123',
                            'quantity' => 1,
                            'price' => [
                                'id' => 'price_test_premium',
                                'product' => 'prod_test_premium',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Send a webhook request signed like Stripe does (HMAC-SHA256 of "timestamp.payload").
 */
function postSignedStripeWebhook(Tests\TestCase $testCase, array $payload, string $signingSecret): Illuminate\Testing\TestResponse
{
    $jsonPayload = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $signingSecret);

    return $testCase->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $jsonPayload);
}

test('the stripe webhook is excluded from CSRF verification', function () {
    $webhookRequest = Request::create('/stripe/webhook', 'POST');
    // The middleware adds the XSRF cookie to responses, which requires a session
    $webhookRequest->setLaravelSession(app('session.store'));

    $response = productionLikeCsrfMiddleware()->handle($webhookRequest, fn () => response('passed'));

    expect($response->getContent())->toBe('passed');
});

test('other POST routes still require a CSRF token', function () {
    $profileRequest = Request::create('/profile', 'POST');
    $profileRequest->setLaravelSession(app('session.store'));

    productionLikeCsrfMiddleware()->handle($profileRequest, fn () => response('passed'));
})->throws(TokenMismatchException::class);

test('webhook events are rejected when the signing secret is not configured', function () {
    config(['cashier.webhook.secret' => null]);
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    // Signed with an empty key: exactly what an attacker could forge without a secret
    $response = postSignedStripeWebhook($this, subscriptionCreatedEventPayload('cus_test_123', 'premium'), '');

    $response->assertStatus(500);
    expect($user->subscriptions()->count())->toBe(0);
});

test('webhook events with an invalid signature are rejected', function () {
    config(['cashier.webhook.secret' => TEST_STRIPE_WEBHOOK_SECRET]);
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $response = postSignedStripeWebhook($this, subscriptionCreatedEventPayload('cus_test_123', 'premium'), 'wrong_secret');

    $response->assertForbidden();
    expect($user->subscriptions()->count())->toBe(0);
});

test('a signed subscription created event grants premium features', function () {
    config(['cashier.webhook.secret' => TEST_STRIPE_WEBHOOK_SECRET]);
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $response = postSignedStripeWebhook($this, subscriptionCreatedEventPayload('cus_test_123', 'premium'), TEST_STRIPE_WEBHOOK_SECRET);

    $response->assertOk();
    expect($user->fresh()->subscribed('premium'))->toBeTrue();
    expect(Feature::for($user->fresh())->active('analytics.portfolio'))->toBeTrue();
});
