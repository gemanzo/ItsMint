<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

/**
 * Base for test doubles of Cashier's Subscription: keep Cashier's table and foreign
 * key, which are otherwise derived from the (test) class name.
 */
abstract class TestSubscription extends Subscription
{
    protected $table = 'subscriptions';

    public function getForeignKey()
    {
        return 'subscription_id';
    }
}

/**
 * Subscription model whose Stripe cancellation always succeeds (no network call).
 */
class CancellableTestSubscription extends TestSubscription
{
    public static int $cancelledSubscriptionsCount = 0;

    public function cancelNow()
    {
        self::$cancelledSubscriptionsCount++;
        $this->forceFill(['stripe_status' => 'canceled', 'ends_at' => now()])->save();

        return $this;
    }
}

/**
 * Subscription model whose Stripe cancellation always fails (e.g. Stripe unreachable).
 */
class UncancellableTestSubscription extends TestSubscription
{
    public function cancelNow()
    {
        throw new RuntimeException('Stripe is unreachable');
    }
}

function createPremiumUser(): User
{
    $premiumUser = User::factory()->create(['stripe_id' => 'cus_test_deletion']);
    $premiumUser->subscriptions()->create([
        'type' => 'premium',
        'stripe_id' => 'sub_test_deletion',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
    ]);

    return $premiumUser;
}

afterEach(function () {
    Cashier::useSubscriptionModel(Subscription::class);
    CancellableTestSubscription::$cancelledSubscriptionsCount = 0;
});

test('deleting the account cancels the subscription and removes the user data', function () {
    Cashier::useSubscriptionModel(CancellableTestSubscription::class);
    $premiumUser = createPremiumUser();
    Storage::disk('s3-card-scans')->put("card-scans/{$premiumUser->id}/scan.jpg", 'image bytes');

    $response = $this->actingAs($premiumUser)->delete('/profile', ['password' => 'password']);

    $response->assertRedirect('/');
    expect(CancellableTestSubscription::$cancelledSubscriptionsCount)->toBe(1);
    expect($premiumUser->fresh())->toBeNull();
    expect(Subscription::where('user_id', $premiumUser->id)->exists())->toBeFalse();
    Storage::disk('s3-card-scans')->assertMissing("card-scans/{$premiumUser->id}/scan.jpg");
});

test('the account is kept when the subscription cannot be cancelled', function () {
    Cashier::useSubscriptionModel(UncancellableTestSubscription::class);
    $premiumUser = createPremiumUser();

    $response = $this->actingAs($premiumUser)
        ->from('/profile')
        ->delete('/profile', ['password' => 'password']);

    $response->assertSessionHasErrors('account');
    expect($premiumUser->fresh())->not->toBeNull();
    $this->assertAuthenticated();
});
