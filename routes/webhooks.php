<?php

use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Cashier as StripeCashier;
use Forgeify\BillingPortal\Http\Controllers\StripeWebhook;

Route::group([
    'prefix' => config('billing-portal.prefix'),
    'as' => 'billing-portal.',
    'middleware' => config('billing-portal.webhooks.middleware'),
], function () {
    if (class_exists(StripeCashier::class)) {
        Route::post(
            config('billing-portal.webhooks.stripe.path', '/stripe/webhook'),
            [
                config('billing-portal.webhooks.stripe.class', StripeWebhook::class),
                'handleWebhook',
            ]
        )->name('stripe.webhook');
    }
});
