<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->string('ip_address');
            $table->enum('type', ['enhance'])->default('enhance');
            $table->string('api_endpoint');
            $table->text('api_token'); // encrypt via cast in the model
            $table->unsignedInteger('max_accounts')->nullable();
            $table->unsignedInteger('active_accounts')->default(0);
            $table->enum('status', ['active', 'maintenance', 'full', 'disabled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
