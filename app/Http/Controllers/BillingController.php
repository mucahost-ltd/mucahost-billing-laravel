<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Requests\UpdateHostingPlanRequest;
use App\Jobs\ProvisionService;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\ConfirmPaymentService;
use App\Services\OrderProvisioningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        return Inertia::render('billing', [
            'view' => 'overview',
            'stats' => $this->invoiceCounts(),
            'moneyTotals' => Invoice::query()
                ->join('currencies', 'currencies.id', '=', 'invoices.currency_id')
                ->select(['currencies.code', 'currencies.symbol'])
                ->selectRaw('SUM(invoices.total) as total')
                ->selectRaw('AVG(invoices.total) as average')
                ->groupBy('currencies.id', 'currencies.code', 'currencies.symbol')
                ->orderBy('currencies.code')
                ->get()
                ->map(fn (Invoice $summary): array => [
                    'code' => (string) $summary->getAttribute('code'),
                    'symbol' => (string) $summary->getAttribute('symbol'),
                    'total' => number_format((float) $summary->getAttribute('total'), 2, '.', ''),
                    'average' => number_format((float) $summary->getAttribute('average'), 2, '.', ''),
                ]),
            'invoiceVolume' => $this->invoiceVolume(),
            'recentInvoices' => Invoice::query()
                ->with(['client:id,first_name,last_name,email', 'currency:id,code,symbol', 'items:id,invoice_id,description'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Invoice $invoice): array => $this->invoiceData($invoice)),
        ]);
    }

    public function invoices(Request $request): Response
    {
        abort_unless($request->user('web')?->role === 'admin', 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['paid', 'unpaid', 'overdue', 'cancelled', 'refunded', 'last_30_days'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['issued', 'due', 'amount', 'invoice'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'issued';
        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = match ($sort) {
            'due' => 'due_date',
            'amount' => 'total',
            'invoice' => 'invoice_number',
            default => 'created_at',
        };

        $invoices = Invoice::query()
            ->with(['client:id,first_name,last_name,email', 'currency:id,code,symbol', 'items:id,invoice_id,description'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->whereAny(
                    ['first_name', 'last_name', 'email', 'company_name'],
                    'like',
                    "%{$search}%",
                ))))
            ->when($status === 'paid', fn (Builder $query) => $query->where('status', 'paid'))
            ->when($status === 'unpaid', fn (Builder $query) => $query
                ->where('status', 'unpaid')
                ->whereDate('due_date', '>=', today()))
            ->when($status === 'overdue', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('status', 'overdue')
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', 'unpaid')
                    ->whereDate('due_date', '<', today()))))
            ->when(in_array($status, ['cancelled', 'refunded'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($status === 'last_30_days', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->invoiceData($invoice));

        return Inertia::render('billing', [
            'view' => 'invoices',
            'invoices' => $invoices,
            'stats' => $this->invoiceCounts(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function confirm(ConfirmPaymentRequest $request, Invoice $invoice, ConfirmPaymentService $payments): RedirectResponse
    {
        $payments->confirm($invoice, $request->user('web'), $request->validated('reference'));

        return back();
    }

    public function updatePlan(UpdateHostingPlanRequest $request, Product $product): RedirectResponse
    {
        abort_unless($product->module === 'enhance' && $product->type === 'shared_hosting', 404);
        $product->update(['module_settings' => [...($product->module_settings ?? []), 'plan_id' => $request->integer('plan_id')]]);

        return back();
    }

    public function accept(Request $request, Order $order, OrderProvisioningService $provisioning): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);
        $provisioning->accept($order);

        return back();
    }

    public function activate(Request $request, Service $service, OrderProvisioningService $provisioning): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);
        $provisioning->activate($service);

        return back();
    }

    public function retry(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user('web')?->role === 'admin', 403);
        abort_unless($service->status === 'pending' && in_array($service->provisioning_status, ['queued', 'failed'], true), 409);
        ProvisionService::dispatch($service);

        return back();
    }

    /** @return array{all: int, paid: int, unpaid: int, overdue: int, cancelled: int, refunded: int, last_30_days: int} */
    private function invoiceCounts(): array
    {
        return [
            'all' => Invoice::query()->count(),
            'paid' => Invoice::query()->where('status', 'paid')->count(),
            'unpaid' => Invoice::query()->where('status', 'unpaid')->whereDate('due_date', '>=', today())->count(),
            'overdue' => Invoice::query()->where(fn (Builder $query) => $query
                ->where('status', 'overdue')
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', 'unpaid')
                    ->whereDate('due_date', '<', today())))->count(),
            'cancelled' => Invoice::query()->where('status', 'cancelled')->count(),
            'refunded' => Invoice::query()->where('status', 'refunded')->count(),
            'last_30_days' => Invoice::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /** @return list<array{key: string, label: string, count: int}> */
    private function invoiceVolume(): array
    {
        $counts = Invoice::query()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at'])
            ->countBy(fn (Invoice $invoice): string => $invoice->created_at->format('Y-m'));

        $volume = [];

        foreach (range(5, 0) as $monthsAgo) {
            $month = now()->subMonths($monthsAgo);
            $volume[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'count' => (int) $counts->get($month->format('Y-m'), 0),
            ];
        }

        return $volume;
    }

    /**
     * @return array{id: int, invoice_number: string, status: string, total: string, due_date: string, issued_at: string, paid_at: string|null, is_overdue: bool, item_summary: string|null, item_count: int, client: array{id: int, name: string, initials: string, email: string}, currency: array{code: string, symbol: string}}
     */
    private function invoiceData(Invoice $invoice): array
    {
        $isOverdue = $invoice->status === 'overdue'
            || ($invoice->status === 'unpaid' && $invoice->due_date->isBefore(today()));

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $isOverdue ? 'overdue' : $invoice->status,
            'total' => $invoice->total,
            'due_date' => $invoice->due_date->toDateString(),
            'issued_at' => $invoice->created_at->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateString(),
            'is_overdue' => $isOverdue,
            'item_summary' => $invoice->items->first()?->description,
            'item_count' => $invoice->items->count(),
            'client' => [
                'id' => $invoice->client->id,
                'name' => trim($invoice->client->first_name.' '.$invoice->client->last_name),
                'initials' => strtoupper(substr($invoice->client->first_name, 0, 1).substr($invoice->client->last_name, 0, 1)),
                'email' => $invoice->client->email,
            ],
            'currency' => [
                'code' => $invoice->currency->code,
                'symbol' => $invoice->currency->symbol,
            ],
        ];
    }
}
