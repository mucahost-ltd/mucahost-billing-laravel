<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypt via cast in the model
            $table->enum('status', ['pending', 'active', 'suspended', 'terminated', 'cancelled'])
                ->default('pending');
            $table->string('billing_cycle');
            $table->decimal('amount', 10, 2);
            $table->date('next_due_date')->nullable();
            $table->string('enhance_account_id')->nullable(); // external ref on the Enhance panel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
