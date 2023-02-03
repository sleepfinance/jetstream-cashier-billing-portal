<?php

namespace Forgeify\BillingPortal\Http\Controllers\Livewire;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Forgeify\BillingPortal\BillingPortal;

class BillingController extends Controller
{
    /**
     * Redirect the user to the subscriptions page.
     *
     * @return \Illuminate\Http\Response
     */
    public function dashboard()
    {
        return Redirect::route('billing-portal.subscription.index');
    }

    /**
     * Redirect to the Stripe portal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function portal(Request $request)
    {
        return $this->getBillingPortalRedirect(
            BillingPortal::getBillable($request)
        );
    }

    /**
     * Get the billing portal redirect response.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @return Illuminate\Routing\Redirector|\Illuminate\Http\Response
     */
    protected function getBillingPortalRedirect($billable)
    {
        $billable->createOrGetStripeCustomer();

        return Redirect::to($billable->billingPortalUrl(route('billing-portal.dashboard')));
    }
}
