<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('billing_type')->default('recurring');
            $table->string('setup_mode')->default('after_payment');
            $table->boolean('is_visible')->default(true);
            $table->boolean('requires_domain')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });
        Schema::table('product_pricing', function (Blueprint $table) {
            $table->unique(['product_id', 'currency_id', 'billing_cycle'], 'product_pricing_cycle_unique');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('provisioning_settings')->nullable();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->json('provisioning_settings')->nullable();
            $table->timestamp('provisioning_authorized_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('accepted_at'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['provisioning_settings', 'provisioning_authorized_at']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('provisioning_settings'));
        Schema::table('product_pricing', fn (Blueprint $table) => $table->dropUnique('product_pricing_cycle_unique'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['billing_type', 'setup_mode', 'is_visible', 'requires_domain', 'sort_order']));
        Schema::table('product_groups', fn (Blueprint $table) => $table->dropColumn('is_visible'));
    }
};
