<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_token')->nullable()->unique();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('confirmation_reference', 190)->nullable()->unique();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('provisioning_status')->default('queued');
            $table->string('provisioning_step')->nullable();
            $table->text('provisioning_error')->nullable();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('enhance_org_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('enhance_org_pending'));
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn(['provisioning_status', 'provisioning_step', 'provisioning_error']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmation_reference');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('checkout_token'));
    }
};
