<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $client = $request->user('client');

        return Inertia::render('client/dashboard', [
            'services' => $client->services()->with('product:id,name')->get(['id', 'product_id', 'domain', 'status', 'provisioning_status']),
            'invoices' => $client->invoices()->with('currency:id,code')->latest()->take(5)->get(['id', 'currency_id', 'invoice_number', 'status', 'total']),
            'openTickets' => $client->tickets()->where('status', '!=', 'closed')->count(),
        ]);
    }
}
