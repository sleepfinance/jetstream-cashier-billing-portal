<?php

namespace Forgeify\BillingPortal\Http\Controllers\Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Forgeify\BillingPortal\BillingPortal;
use Forgeify\BillingPortal\Contracts\HandleSubscriptions;
use Forgeify\CashierRegister\Saas;

class SubscriptionController extends Controller
{
    /**
     * Initialize the controller.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $request->merge([
            'subscription' => $request->subscription ?: 'main',
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $billable = BillingPortal::getBillable($request);

        $subscription = $this->getCurrentSubscription($billable, $request->subscription);

        return Inertia::render('BillingPortal/Subscription/Index', [
            'currentPlan' => $subscription ? $subscription->getPlan() : null,
            'hasDefaultPaymentMethod' => $billable->hasDefaultPaymentMethod(),
            'paymentMethods' => $billable->paymentMethods(),
            'plans' => Saas::getPlans(),
            'recurring' => $subscription ? $subscription->recurring() : false,
            'cancelled' => $subscription ? $subscription->cancelled() : false,
            'onGracePeriod' => $subscription ? $subscription->onGracePeriod() : false,
            'endingDate' => $subscription ? optional($subscription->ends_at)->format('d M Y \a\t H:i') : null,
        ]);
    }

    /**
     * Redirect the user to subscribe to the plan.
     *
     * @param  \Forgeify\BillingPortal\Contracts\HandleSubscriptions  $manager
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $planId
     * @return \Illuminate\Http\Response
     */
    public function redirectWithSubscribeIntent(HandleSubscriptions $manager, Request $request, string $planId)
    {
        $billable = BillingPortal::getBillable($request);

        $plan = Saas::getPlan($planId);

        $subscription = $billable->newSubscription($request->subscription, $plan->getId());

        $checkout = $manager->checkoutOnSubscription(
            $subscription, $billable, $plan, $request
        );

        return view('jetstream-cashier-billing-portal::checkout', [
            'checkout' => $checkout,
            'stripeKey' => config('cashier.key'),
        ]);
    }

    /**
     * Swap the plan to a new one.
     *
     * @param  \Forgeify\BillingPortal\Contracts\HandleSubscriptions  $manager
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $newPlanId
     * @return \Illuminate\Http\Response
     */
    public function swapPlan(HandleSubscriptions $manager, Request $request, string $newPlanId)
    {
        $newPlan = Saas::getPlan($newPlanId);
        $billable = BillingPortal::getBillable($request);

        if (! $subscription = $this->getCurrentSubscription($billable, $request->subscription)) {
            return Redirect::route('billing-portal.subscription.index')
                ->with('flash.banner', "The subscription {$request->subscription} does not exist.")
                ->with('flash.bannerStyle', 'danger');
        }

        // If the desired plan has a price and the user has no payment method added to its account,
        // redirect it to the Checkout page to finish the payment info & subscribe.
        if ($newPlan->getPrice() > 0.00 && ! $billable->defaultPaymentMethod()) {
            return $this->redirectWithSubscribeIntent($manager, $request, $newPlan->getId());
        }

        // Otherwise, check if it is not already subscribed to the new plan and initiate
        // a plan swapping. It also takes proration into account.
        if (! $billable->subscribed($subscription->name, $newPlan->getId())) {
            $hasValidSubscription = $subscription && $subscription->valid();

            $subscription = value(function () use ($hasValidSubscription, $subscription, $newPlan, $request, $billable, $manager) {
                if ($hasValidSubscription) {
                    return $manager->swapToPlan($subscription, $billable, $newPlan, $request);
                }

                // However, this is the only place where a ->create() method is involved. At this point, the user has
                // a default payment method set and we will initialize the subscription in case it is not subscribed
                // to a plan with the given subscription name.
                return $manager->subscribeToPlan(
                    $billable, $newPlan, $request
                );
            });
        }

        return Redirect::route('billing-portal.subscription.index')
            ->with('flash.banner', "The plan got successfully changed to {$newPlan->getName()}!");
    }

    /**
     * Resume the current cancelled subscription.
     *
     * @param  \Forgeify\BillingPortal\Contracts\HandleSubscriptions  $manager
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function resumeSubscription(HandleSubscriptions $manager, Request $request)
    {
        $billable = BillingPortal::getBillable($request);

        if (! $subscription = $this->getCurrentSubscription($billable, $request->subscription)) {
            return Redirect::route('billing-portal.subscription.index')
                ->with('flash.banner', "The subscription {$request->subscription} does not exist.")
                ->with('flash.bannerStyle', 'danger');
        }

        $manager->resumeSubscription($subscription, $billable, $request);

        return Redirect::route('billing-portal.subscription.index')
            ->with('flash.banner', 'The subscription has been resumed.');
    }

    /**
     * Cancel the current active subscription.
     *
     * @param  \Forgeify\BillingPortal\Contracts\HandleSubscriptions  $manager
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cancelSubscription(HandleSubscriptions $manager, Request $request)
    {
        $billable = BillingPortal::getBillable($request);

        if (! $subscription = $this->getCurrentSubscription($billable, $request->subscription)) {
            return Redirect::route('billing-portal.subscription.index')
                ->with('flash.banner', "The subscription {$request->subscription} does not exist.")
                ->with('flash.bannerStyle', 'danger');
        }

        $manager->cancelSubscription($subscription, $billable, $request);

        return Redirect::route('billing-portal.subscription.index')
            ->with('flash.banner', 'The current subscription got cancelled!');
    }

    /**
     * Get the current billable subscription.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @param  string  $subscription
     * @return \Laravel\Cashier\Subscription|null
     */
    protected function getCurrentSubscription($billable, string $subscription)
    {
        return $billable->subscription($subscription);
    }
}
