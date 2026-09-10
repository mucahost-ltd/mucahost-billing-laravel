<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    // Tier name => [monthly USD, annually USD, module_settings for Enhance]
    protected array $sharedTiers = [
        'Starter' => [3,  30, ['plan' => 'shared-starter',  'disk_gb' => 5,  'sites' => 1]],
        'Business' => [6,  60, ['plan' => 'shared-business', 'disk_gb' => 20, 'sites' => 10]],
        'Pro' => [12, 120, ['plan' => 'shared-pro',     'disk_gb' => 50, 'sites' => -1]],
    ];

    protected array $wordpressTiers = [
        'Starter' => [5,  50, ['plan' => 'wp-starter',  'disk_gb' => 10, 'visits' => 25000]],
        'Business' => [10, 100, ['plan' => 'wp-business', 'disk_gb' => 30, 'visits' => 100000]],
        'Pro' => [20, 200, ['plan' => 'wp-pro',      'disk_gb' => 60, 'visits' => 500000]],
    ];

    protected array $nodeTiers = [
        'Starter' => [7,  70, ['plan' => 'node-starter',  'ram_mb' => 512,  'apps' => 1]],
        'Business' => [14, 140, ['plan' => 'node-business', 'ram_mb' => 1024, 'apps' => 3]],
        'Pro' => [28, 280, ['plan' => 'node-pro',      'ram_mb' => 2048, 'apps' => 10]],
    ];

    public function run(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->seedGroup('Shared Hosting', 'shared_hosting', $this->sharedTiers, $usd);
        $this->seedGroup('WordPress Hosting', 'shared_hosting', $this->wordpressTiers, $usd);
        $this->seedGroup('Node.js Hosting', 'vps', $this->nodeTiers, $usd);
    }

    protected function seedGroup(string $groupName, string $productType, array $tiers, Currency $usd): void
    {
        $group = ProductGroup::updateOrCreate(
            ['slug' => Str::slug($groupName)],
            ['name' => $groupName, 'sort_order' => 0]
        );

        foreach ($tiers as $tierName => [$monthly, $annually, $moduleSettings]) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug("{$groupName}-{$tierName}")],
                [
                    'product_group_id' => $group->id,
                    'name' => "{$groupName} — {$tierName}",
                    'type' => $productType,
                    'module' => 'enhance',
                    'module_settings' => $moduleSettings,
                    'is_active' => true,
                ]
            );

            $product->pricing()->updateOrCreate(
                ['billing_cycle' => 'monthly', 'currency_id' => $usd->id],
                ['price' => $monthly, 'setup_fee' => 0]
            );

            $product->pricing()->updateOrCreate(
                ['billing_cycle' => 'annually', 'currency_id' => $usd->id],
                ['price' => $annually, 'setup_fee' => 0]
            );
        }
    }
}
