# SaaS Setup Guide

This guide explains how to set up and configure the SaaS subscription system for ItsMint.

## Overview

ItsMint uses Laravel Cashier for Stripe integration and Laravel Pennant for feature flag management. The system supports a Free plan with limitations and a Premium plan with full access.

## Free Plan Limitations

- **1 Portfolio**: Only the oldest portfolio is accessible and editable
- **50 Cards Total**: Maximum 50 cards across all portfolios
- **Basic Search**: Maximum 10 results, no advanced filters
- **No CSV Export**: Sales data export is disabled
- **No Analytics**: Portfolio performance charts are disabled
- **No Price Alerts**: Price change notifications are disabled

## Premium Plan Features

- **Unlimited Portfolios**: Create as many portfolios as needed
- **Unlimited Cards**: No limits on collection size
- **Advanced Search**: Up to 50 results with filters (expansion, language, sorting)
- **CSV Export**: Export sales history with filters
- **Portfolio Analytics**: Performance charts, ROI tracking, value history
- **Price Alerts**: Get notified when card prices change (coming soon)

## Stripe Setup

### 1. Create Stripe Account

1. Sign up at [stripe.com](https://stripe.com)
2. Complete account verification
3. Switch to live mode when ready for production

### 2. Create Premium Product and Price

1. Go to Stripe Dashboard > Products
2. Create a new product named "Premium"
3. Add a recurring price (monthly or yearly)
4. Copy the Price ID (starts with `price_`)
5. Add it to your `.env` file:

```env
STRIPE_PRICE_ID_PREMIUM=price_xxxxx
```

### 3. Configure Stripe Keys

Add your Stripe keys to `.env`:

```env
STRIPE_KEY=pk_test_xxxxx  # or pk_live_xxxxx for production
STRIPE_SECRET=sk_test_xxxxx  # or sk_live_xxxxx for production
```

### 4. Configure Webhook Endpoint

1. Go to Stripe Dashboard > Developers > Webhooks
2. Click "Add endpoint"
3. Enter your webhook URL: `https://yourdomain.com/stripe/webhook`
4. Select these events to listen to:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
5. Copy the webhook signing secret (starts with `whsec_`)
6. Add it to your `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### 5. Configure Cashier

The Cashier configuration is in `config/cashier.php`. Default settings should work, but verify:

- `currency` is set to `EUR` (or your preferred currency)
- `webhook.secret` matches your `.env` value

## Testing

### Local Testing with Stripe CLI

1. Install Stripe CLI: https://stripe.com/docs/stripe-cli
2. Login: `stripe login`
3. Forward webhooks to local server:
   ```bash
   stripe listen --forward-to localhost:8000/stripe/webhook
   ```
4. Trigger test events:
   ```bash
   stripe trigger customer.subscription.created
   stripe trigger customer.subscription.updated
   stripe trigger customer.subscription.deleted
   ```

### Test Cards

Use Stripe test cards:
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- 3D Secure: `4000 0027 6000 3184`

## Feature Flags

Features are managed using Laravel Pennant and defined in `app/Providers/AppServiceProvider.php`:

- `portfolio.unlimited`: Unlimited portfolios
- `search.advanced`: Advanced search features
- `export.csv`: CSV export functionality
- `analytics.portfolio`: Portfolio analytics
- `alerts.price`: Price alerts (coming soon)

Features are automatically invalidated when subscription status changes via webhooks.

## Subscription Flow

1. User clicks "Upgrade to Premium"
2. Frontend calls `/subscribe/checkout` POST endpoint
3. Backend creates Stripe Checkout Session
4. User redirected to Stripe Checkout
5. After payment, Stripe redirects to `/subscribe?success=1`
6. Webhook handler processes subscription events
7. Feature flags are invalidated and refreshed
8. User gets access to premium features

## Monitoring

### Check Subscription Health

Monitor subscription status in Stripe Dashboard:
- Active subscriptions
- Failed payments
- Cancellations

### Logs

Subscription events are logged in `storage/logs/laravel.log`:
- Subscription created/updated/deleted
- Payment succeeded/failed
- Feature cache invalidation

## Troubleshooting

### Webhook Not Working

1. Verify webhook secret matches `.env`
2. Check webhook endpoint is accessible (HTTPS in production)
3. Verify webhook events are selected in Stripe Dashboard
4. Check Laravel logs for errors

### Features Not Updating

1. Verify subscription is active: `$user->subscribed('premium')`
2. Check feature definitions in `AppServiceProvider`
3. Manually flush cache: `Feature::for($user)->flush()`
4. Verify webhook is processing events

### Checkout Fails

1. Verify `STRIPE_PRICE_ID_PREMIUM` is set correctly
2. Check Stripe keys are valid
3. Verify user has Stripe customer ID
4. Check Laravel logs for error details

## Environment Variables Checklist

Make sure these are set in `.env`:

```env
STRIPE_KEY=pk_test_xxxxx
STRIPE_SECRET=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
STRIPE_PRICE_ID_PREMIUM=price_xxxxx
```

## Production Deployment Checklist

- [ ] Create Stripe live account
- [ ] Create Premium product and price in Stripe
- [ ] Configure live Stripe keys in production `.env`
- [ ] Configure webhook endpoint in Stripe Dashboard (production URL)
- [ ] Test webhook endpoint with Stripe CLI or Dashboard
- [ ] Verify HTTPS is enabled for webhook endpoint
- [ ] Test complete subscription flow end-to-end
- [ ] Monitor subscription events in Stripe Dashboard
- [ ] Set up error monitoring for failed payments
- [ ] Document support procedures for billing issues

## Support

For billing-related issues:
1. Check Stripe Dashboard for payment status
2. Review Laravel logs for errors
3. Verify webhook events are being received
4. Contact Stripe support if needed
