<?php

namespace Database\Seeders;

use App\Models\SupportDepartment;
use Illuminate\Database\Seeder;

class SupportDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Sales', 'email' => 'sales@mucahost.com'],
            ['name' => 'Billing', 'email' => 'billing@mucahost.com'],
            ['name' => 'Technical Support', 'email' => 'support@mucahost.com'],
        ] as $dept) {
            SupportDepartment::updateOrCreate(['name' => $dept['name']], $dept);
        }
    }
}
