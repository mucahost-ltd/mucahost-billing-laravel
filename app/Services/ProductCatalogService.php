<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product) {
            ProductGroup::query()->lockForUpdate()->findOrFail($data['product_group_id']);
            $product = $product ? Product::query()->lockForUpdate()->findOrFail($product->id) : new Product;
            $settings = $product->module_settings ?? [];
            unset($settings['plan'], $settings['plan_id'], $settings['app_server_id']);
            if ($data['module'] === 'enhance') {
                $settings['plan_id'] = (int) $data['plan_id'];
                if (! empty($data['app_server_id'])) {
                    $settings['app_server_id'] = $data['app_server_id'];
                }
            }
            $product->fill(collect($data)->except(['pricing', 'plan_id', 'app_server_id'])->all());
            $product->module_settings = $settings;
            $product->save();
            $keep = [];
            foreach ($data['pricing'] as $price) {
                $row = $product->pricing()->updateOrCreate(
                    ['currency_id' => $price['currency_id'], 'billing_cycle' => $price['billing_cycle']],
                    ['price' => $price['price'], 'setup_fee' => $price['setup_fee']],
                );
                $keep[] = $row->id;
            }
            $product->pricing()->whereNotIn('id', $keep)->delete();

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($product->orderItems()->exists() || $product->services()->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has orders or services. Disable it instead to preserve billing history.']);
            }
            $product->delete();
        });
    }
}
