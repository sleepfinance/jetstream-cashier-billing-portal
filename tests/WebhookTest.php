<?php

namespace Forgeify\BillingPortal\Test;

use Forgeify\BillingPortal\Test\Models\User;
use Forgeify\CashierREgister\Saas;

class WebhookTest extends TestCase
{
    public function test_webhook_for_invoice_payment_succeeded()
    {
        $user = factory(User::class)->create();

        $plan = Saas::getPlan(static::$stripeFreePlanId);

        $subscription = $this->createStripeSubscription($user, $plan);

        $this->postJson(route('billing-portal.stripe.webhook'), [
            'id' => 'foo',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'customer' => $user->stripe_id,
                    'subscription' => $subscription->stripe_id,
                ],
            ],
        ])->assertOk();
    }
}
