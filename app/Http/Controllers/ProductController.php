<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductCatalogService;
use GoSuccess\Enhance\Enhance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'groups' => ProductGroup::query()->with(['products' => fn ($query) => $query->withCount('services')->with('pricing.currency')->orderBy('sort_order')->orderBy('id')])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return $this->editor(null);
    }

    public function edit(Product $product): Response
    {
        return $this->editor($product);
    }

    private function editor(?Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product?->load('pricing'),
            'groups' => ProductGroup::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'currencies' => Currency::query()->orderByDesc('is_default')->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function store(SaveProductRequest $request, ProductCatalogService $catalog): RedirectResponse
    {
        return redirect()->route('products.edit', $catalog->save($request->validated()));
    }

    public function update(SaveProductRequest $request, Product $product, ProductCatalogService $catalog): RedirectResponse
    {
        $catalog->save($request->validated(), $product);

        return back();
    }

    public function destroy(Product $product, ProductCatalogService $catalog): RedirectResponse
    {
        $catalog->delete($product);

        return redirect()->route('products.index');
    }

    public function plans(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        $page = $data['page'] ?? 1;
        try {
            $listing = app(Enhance::class)->plans->getPlans(offset: ($page - 1) * 100, limit: 100);
            if (! $listing) {
                throw new \RuntimeException('Missing plan listing');
            }

            return response()->json([
                'plans' => array_map(fn ($plan): array => ['id' => $plan->id, 'name' => $plan->name], $listing->items ?? []),
                'next_page' => ($listing->total ?? 0) > $page * 100 ? $page + 1 : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Could not load Enhance plans. Check your panel connection, or enter an existing plan ID.'], 502);
        }
    }
}
