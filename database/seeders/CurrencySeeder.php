<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::updateOrCreate(
            ['code' => 'USD'],
            ['symbol' => '$', 'exchange_rate' => 1, 'is_default' => true]
        );

        Currency::updateOrCreate(
            ['code' => 'BDT'],
            ['symbol' => '৳', 'exchange_rate' => 122.5, 'is_default' => false]
        );
    }
}
