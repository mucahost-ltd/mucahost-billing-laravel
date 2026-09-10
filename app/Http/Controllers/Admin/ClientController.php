<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        return Inertia::render('clients/form', [
            'client' => null,
            'currencies' => Currency::query()->orderByDesc('is_default')->orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $client = Client::create($this->validatedData($request));

        return redirect()->route('clients.show', $client)->with('success', 'Client created successfully.');
    }

    public function edit(Request $request, Client $client): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        return Inertia::render('clients/form', [
            'client' => $client->only([
                'id', 'first_name', 'last_name', 'email', 'phone', 'company_name',
                'address1', 'address2', 'city', 'state', 'postcode', 'country',
                'currency_id', 'status', 'admin_notes',
            ]),
            'currencies' => Currency::query()->orderByDesc('is_default')->orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $client->update($this->validatedData($request, $client));

        return redirect()->route('clients.show', $client)->with('success', 'Client profile updated.');
    }

    public function updateStatus(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $client->update($request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'closed'])],
        ]));

        return back()->with('success', 'Client status updated.');
    }

    public function storeNote(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $client->notes()->create([
            'user_id' => $request->user('web')->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Note added.');
    }

    public function destroyNote(Request $request, Client $client, ClientNote $note): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);
        abort_unless($note->client_id === $client->id, 404);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Client $client = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'closed'])],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'password' => [$client ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['country'] = filled($data['country'] ?? null) ? Str::upper($data['country']) : null;

        return $data;
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'closed'])],
            'country' => ['nullable', 'string', 'size:2'],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'services', 'invoices'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        [$startDate, $endDate] = $this->dateRange($filters);
        $sortColumn = match ($sort) {
            'name' => 'first_name',
            'services' => 'services_count',
            'invoices' => 'invoices_count',
            default => 'created_at',
        };

        $clients = Client::query()
            ->with('currency:id,code,symbol')
            ->withCount(['services', 'invoices', 'tickets'])
            ->when($search !== '', fn (Builder $query) => $query->whereAny(
                ['first_name', 'last_name', 'email', 'company_name', 'phone'],
                'like',
                "%{$search}%",
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['country'] ?? null, fn (Builder $query, string $country) => $query->where('country', strtoupper($country)))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => trim($client->first_name.' '.$client->last_name),
                'initials' => strtoupper(substr($client->first_name, 0, 1).substr($client->last_name, 0, 1)),
                'email' => $client->email,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'country' => $client->country,
                'status' => $client->status,
                'credit_balance' => $client->credit_balance,
                'admin_notes' => $client->admin_notes,
                'currency' => $client->currency?->only(['code', 'symbol']),
                'services_count' => $client->services_count,
                'invoices_count' => $client->invoices_count,
                'tickets_count' => $client->tickets_count,
                'created_at' => $client->created_at?->toDateString(),
            ]);

        $statusCounts = Client::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('clients/index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? null,
                'country' => isset($filters['country']) ? strtoupper($filters['country']) : null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => [
                'all' => Client::query()->count(),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'inactive' => (int) ($statusCounts['inactive'] ?? 0),
                'closed' => (int) ($statusCounts['closed'] ?? 0),
            ],
            'countries' => Client::query()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
            'dateRange' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'overview' => [
                'new_clients' => Client::query()->whereBetween('created_at', [$startDate, $endDate])->count(),
                'monthly' => collect(range(0, 11))->map(function (int $offset) use ($endDate): array {
                    $month = $endDate->copy()->startOfMonth()->subMonths(11 - $offset);

                    return [
                        'label' => $month->format('M'),
                        'value' => Client::query()->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
                    ];
                })->values(),
            ],
        ]);
    }

    public function show(Request $request, Client $client): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        [$startDate, $endDate] = $this->dateRange($filters);
        $client->load(['currency:id,code,symbol', 'notes.user:id,name']);
        $invoiceQuery = $client->invoices()->whereBetween('created_at', [$startDate, $endDate]);
        $transactionQuery = $client->transactions()->whereBetween('created_at', [$startDate, $endDate]);
        $orderQuery = $client->orders()->whereBetween('created_at', [$startDate, $endDate]);
        $ticketQuery = $client->tickets()->whereBetween('created_at', [$startDate, $endDate]);
        $serviceQuery = $client->services()->whereBetween('created_at', [$startDate, $endDate]);
        $months = collect(range(0, 11))->map(function (int $offset) use ($endDate): Carbon {
            return $endDate->copy()->startOfMonth()->subMonths(11 - $offset);
        });
        $invoiceSeries = $client->invoices()->whereBetween('created_at', [$startDate, $endDate])->get()
            ->groupBy(fn (Invoice $invoice): string => $invoice->created_at->format('Y-m'));

        return Inertia::render('clients/show', [
            'client' => [
                'id' => $client->id,
                'name' => trim($client->first_name.' '.$client->last_name),
                'initials' => strtoupper(substr($client->first_name, 0, 1).substr($client->last_name, 0, 1)),
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'address' => array_values(array_filter([
                    $client->address1,
                    $client->address2,
                    trim(implode(' ', array_filter([$client->city, $client->state, $client->postcode]))),
                    $client->country,
                ])),
                'country' => $client->country,
                'status' => $client->status,
                'credit_balance' => $client->credit_balance,
                'currency' => $client->currency?->only(['code', 'symbol']),
                'email_verified_at' => $client->email_verified_at?->toDateTimeString(),
                'created_at' => $client->created_at?->toDateString(),
            ],
            'summary' => [
                'invoiced' => (float) $invoiceQuery->sum('total'),
                'invoices' => (int) $invoiceQuery->count(),
                'unpaid' => (float) (clone $invoiceQuery)->whereIn('status', ['unpaid', 'overdue'])->sum('total'),
                'revenue' => (float) (clone $transactionQuery)->where('type', 'payment')->where('status', 'completed')->sum('amount'),
                'orders' => (int) $orderQuery->count(),
                'outstanding' => (float) (clone $invoiceQuery)->whereIn('status', ['unpaid', 'overdue'])->sum('total'),
                'tickets' => (int) $ticketQuery->count(),
                'active_tickets' => (int) (clone $ticketQuery)->whereIn('status', ['open', 'customer-reply'])->count(),
                'services' => (int) $serviceQuery->count(),
                'transactions' => (int) $transactionQuery->count(),
            ],
            'dateRange' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'charts' => [
                'invoiced' => $months->map(fn (Carbon $month): array => ['label' => $month->format('M'), 'value' => (float) ($invoiceSeries->get($month->format('Y-m'))?->sum('total') ?? 0)])->values(),
                'invoices' => $months->map(fn (Carbon $month): array => ['label' => $month->format('M'), 'value' => (int) ($invoiceSeries->get($month->format('Y-m'))?->count() ?? 0)])->values(),
            ],
            'notes' => $client->notes->map(fn (ClientNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->user?->name ?? 'Team member',
                'created_at' => $note->created_at?->toDateTimeString(),
            ]),
            'services' => $client->services()
                ->with('product:id,name')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'product' => $service->product->name,
                    'domain' => $service->domain,
                    'status' => $service->status,
                    'billing_cycle' => $service->billing_cycle,
                    'amount' => $service->amount,
                    'next_due_date' => $service->next_due_date?->toDateString(),
                ]),
            'invoices' => $client->invoices()
                ->with('currency:id,code,symbol')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'currency' => $invoice->currency->code,
                    'due_date' => $invoice->due_date->toDateString(),
                ]),
            'transactions' => $client->transactions()
                ->with(['currency:id,code', 'invoice:id,invoice_number'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Transaction $transaction): array => [
                    'id' => $transaction->id,
                    'invoice_number' => $transaction->invoice?->invoice_number,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'gateway' => $transaction->gateway,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency->code,
                    'created_at' => $transaction->created_at?->toDateString(),
                ]),
            'tickets' => $client->tickets()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Ticket $ticket): array => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'updated_at' => $ticket->updated_at?->toDateString(),
                ]),
        ]);
    }

    /** @param array<string, mixed> $filters @return array{Carbon, Carbon} */
    private function dateRange(array $filters): array
    {
        $end = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfDay();
        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : $end->copy()->subYear()->addDay()->startOfDay();

        return [$start, $end];
    }
}
