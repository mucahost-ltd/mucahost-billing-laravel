<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // An Enhance "org" is the client's whole account on the panel —
            // one org can hold several websites/subscriptions, so this lives
            // on the client, not on an individual service.
            $table->string('enhance_org_id')->nullable()->after('currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('enhance_org_id');
        });
    }
};
