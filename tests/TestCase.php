<?php

namespace Forgeify\BillingPortal\Test;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier as StripeCashier;
use Orchestra\Testbench\TestCase as Orchestra;
use Forgeify\BillingPortal\BillingPortal;
use Forgeify\CashierRegister\Saas;
use Stripe\ApiResource;
use Stripe\Exception\InvalidRequestException;
use Stripe\Plan;
use Stripe\Product;
use Stripe\Stripe;

abstract class TestCase extends Orchestra
{
    protected static $productId;

    protected static $freeProductId;

    protected static $stripePlanId;

    protected static $stripeFreePlanId;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();

        $this->loadLaravelMigrations(['--database' => 'sqlite']);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->withFactories(__DIR__.'/database/factories');

        Saas::plan('Monthly $10', static::$stripePlanId)
            ->price(10, 'USD')
            ->features([
                Saas::feature('Build Minutes', 'build.minutes', 3000),
                Saas::feature('Seats', 'teams', 10)->notResettable(),
            ]);

        Saas::plan('Free Plan', static::$stripeFreePlanId)
            ->features([
                Saas::feature('Build Minutes', 'build.minutes', 10),
                Saas::feature('Seats', 'teams', 5)->notResettable(),
            ]);

        BillingPortal::resolveBillable(function (Request $request) {
            return $request->user();
        });

        if (class_exists(StripeCashier::class)) {
            StripeCashier::useCustomerModel(Models\User::class);
        }

        BillingPortal::resolveAuthorization(function ($billable, Request $request) {
            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Stripe::setApiKey(getenv('STRIPE_SECRET') ?: env('STRIPE_SECRET'));

        static::$stripePlanId = 'monthly-10-'.Str::random(10);

        static::$stripeFreePlanId = 'free-'.Str::random(10);

        static::$productId = 'product-1'.Str::random(10);

        static::$freeProductId = 'product-free'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Product::create([
            'id' => static::$freeProductId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Plan::create([
            'id' => static::$stripePlanId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$stripeFreePlanId,
            'nickname' => 'Free',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 0,
            'product' => static::$freeProductId,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$stripePlanId));
        static::deleteStripeResource(new Plan(static::$stripeFreePlanId));
    }

    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Cashier\CashierServiceProvider::class,
            \Forgeify\CashierRegister\CashierRegisterServiceProvider::class,
            \Forgeify\BillingPortal\BillingPortalServiceProvider::class,
            TestServiceProvider::class,
            \ClaudioDekker\Inertia\InertiaTestingServiceProvider::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'wslxrEFGWY6GfGhvN9L3wH3KSRJQQpBD');
        $app['config']->set('auth.providers.users.model', Models\User::class);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => __DIR__.'/database.sqlite',
            'prefix'   => '',
        ]);

        $app['config']->set('billing-portal.middleware', [
            'web',
            \Forgeify\BillingPortal\Http\Middleware\Authorize::class,
        ]);

        $app['config']->set('cashier.webhook.secret', null);

        $app['config']->set('jetstream.stack', 'inertia');
    }

    /**
     * Reset the database.
     *
     * @return void
     */
    protected function resetDatabase()
    {
        file_put_contents(__DIR__.'/database.sqlite', null);
    }

    /**
     * Create a new subscription.
     *
     * @param  \Forgeify\CashierRegister\Test\Models\Stripe\User  $user
     * @param  \Forgeify\CashierRegister\Plan  $plan
     * @return \Forgeify\CashierRegister\Models\Stripe\Subscription
     */
    protected function createStripeSubscription($user, $plan)
    {
        $subscription = $user->newSubscription('main', $plan->getId());
        $meteredFeatures = $plan->getMeteredFeatures();

        if (! $meteredFeatures->isEmpty()) {
            foreach ($meteredFeatures as $feature) {
                $subscription->meteredPrice($feature->getMeteredId());
            }
        }

        return $subscription->create('pm_card_visa');
    }

    protected static function deleteStripeResource(ApiResource $resource)
    {
        try {
            $resource->delete();
        } catch (InvalidRequestException $e) {
            //
        }
    }
}
