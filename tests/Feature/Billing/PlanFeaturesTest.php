<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Role;

/**
 * Create a local Cashier subscription of the given type and status for the user.
 */
function giveSubscription(User $user, string $subscriptionType, string $stripeStatus, ?Carbon\Carbon $endsAt = null): void
{
    $user->subscriptions()->create([
        'type' => $subscriptionType,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => $stripeStatus,
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
        'ends_at' => $endsAt,
    ]);
}

test('free users do not get premium features', function () {
    $freeUser = User::factory()->create();

    expect(Feature::for($freeUser)->active('portfolio.unlimited'))->toBeFalse();
});

test('users with an active premium subscription get premium features', function () {
    $premiumUser = User::factory()->create();
    giveSubscription($premiumUser, 'premium', 'active');

    expect(Feature::for($premiumUser)->active('portfolio.unlimited'))->toBeTrue();
    expect(Feature::for($premiumUser)->active('export.csv'))->toBeTrue();
});

test('premium features are revoked once the subscription has ended', function () {
    $formerPremiumUser = User::factory()->create();
    giveSubscription($formerPremiumUser, 'premium', 'canceled', now()->subDay());

    expect(Feature::for($formerPremiumUser)->active('portfolio.unlimited'))->toBeFalse();
});

test('admins get premium features', function () {
    Role::create(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect(Feature::for($admin)->active('analytics.portfolio'))->toBeTrue();
});

test('resolved features are never persisted', function () {
    $freeUser = User::factory()->create();

    Feature::for($freeUser)->active('portfolio.unlimited');

    // A persisted value would never be re-evaluated after the user subscribes
    expect(DB::table('features')->count())->toBe(0);
});

test('premium features are shared with the frontend', function () {
    $premiumUser = User::factory()->create();
    giveSubscription($premiumUser, 'premium', 'active');

    $this->actingAs($premiumUser)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('features.portfolio_unlimited', true)
            ->where('features.analytics_portfolio', true)
        );
});

test('premium users cannot start a second checkout', function () {
    $premiumUser = User::factory()->create(['stripe_id' => 'cus_test_123']);
    giveSubscription($premiumUser, 'premium', 'active');

    $response = $this->actingAs($premiumUser)->postJson('/subscribe/checkout');

    $response->assertStatus(409);
});
