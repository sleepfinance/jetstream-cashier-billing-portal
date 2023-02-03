<?php

namespace Forgeify\BillingPortal\Test;

use Forgeify\BillingPortal\Test\Models\User;

class PaymentMethodTest extends TestCase
{
    public function test_payment_methods_index()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->get(route('billing-portal.payment-method.index'))
            ->assertOk();

        $user->createOrGetStripeCustomer();

        $user->addPaymentMethod('pm_card_visa');
        $user->addPaymentMethod('pm_card_mastercard');

        $defaultPaymentMethod = $user->defaultPaymentMethod();

        $methods = $user->paymentMethods()
            ->filter(function ($method) {
                return $method->type === 'card';
            })->map(function ($method) use ($defaultPaymentMethod) {
                return [
                    'default' => $method->id === optional($defaultPaymentMethod)->id,
                    'id' => $method->id,
                    'brand' => $method->card->brand,
                    'last_four' => $method->card->last4,
                    'month' => $method->card->exp_month,
                    'year' => $method->card->exp_year,
                ];
            });

        $this->actingAs($user)
            ->get(route('billing-portal.payment-method.index'))
            ->assertInertia('BillingPortal/PaymentMethod/Index', [
                'methods' => $methods,
            ]);
    }

    public function test_payment_methods_create()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->get(route('billing-portal.payment-method.create'))
            ->assertInertia('BillingPortal/PaymentMethod/Create');
    }

    public function test_payment_methods_store_without_default_method_set()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->post(route('billing-portal.payment-method.store', ['token' => 'pm_card_visa']))
            ->assertRedirect(route('billing-portal.payment-method.index'));

        $this->assertTrue($user->hasDefaultPaymentMethod());
    }

    public function test_payment_methods_store()
    {
        $user = factory(User::class)->create();

        $user->createOrGetStripeCustomer();

        $user->addPaymentMethod('pm_card_visa');

        $user->updateDefaultPaymentMethod('pm_card_visa');

        $this->actingAs($user)
            ->post(route('billing-portal.payment-method.store', ['token' => 'pm_card_mastercard']))
            ->assertRedirect(route('billing-portal.payment-method.index'));
    }

    public function test_payment_methods_destroy()
    {
        $user = factory(User::class)->create();

        $user->createOrGetStripeCustomer();

        $user->addPaymentMethod('pm_card_visa');

        $pm = $user->updateDefaultPaymentMethod('pm_card_visa');

        $this->actingAs($user)
            ->delete(route('billing-portal.payment-method.destroy', ['payment_method' => 'not_existent']))
            ->assertRedirect(route('billing-portal.payment-method.index'));

        $this->actingAs($user)
            ->delete(route('billing-portal.payment-method.destroy', ['payment_method' => $pm->id]))
            ->assertRedirect(route('billing-portal.payment-method.index'));
    }

    public function test_payment_methods_set_default()
    {
        $user = factory(User::class)->create();

        $user->createOrGetStripeCustomer();

        $user->createOrGetStripeCustomer();

        $user->addPaymentMethod('pm_card_visa');
        $pm = $user->addPaymentMethod('pm_card_mastercard');

        $user->updateDefaultPaymentMethod('pm_card_visa');

        $this->actingAs($user)
            ->post(route('billing-portal.payment-method.default', ['payment_method' => 'not_existent']))
            ->assertRedirect(route('billing-portal.payment-method.index'));

        $this->actingAs($user)
            ->post(route('billing-portal.payment-method.default', ['payment_method' => $pm->id]))
            ->assertRedirect(route('billing-portal.payment-method.index'));
    }
}
