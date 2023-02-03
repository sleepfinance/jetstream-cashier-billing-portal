<?php

namespace Forgeify\BillingPortal\Http\Controllers\Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Forgeify\BillingPortal\BillingPortal;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $invoices = BillingPortal::getBillable($request)->invoicesIncludingPending()->map(function ($invoice) {
            return [
                'description' => $invoice->lines->data[0]->description,
                'created' => $invoice->created,
                'paid' => $invoice->paid,
                'status' => $invoice->status,
                'url' => $invoice->hosted_invoice_url ?: null,
            ];
        });

        return Inertia::render('BillingPortal/Invoice/Index', [
            'invoices' => $invoices->toArray(),
        ]);
    }
}
