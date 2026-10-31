<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 4)->default(0.0000);
            $table->string('opening_balance_type')->default('debit'); // debit or credit
            $table->string('status')->default('active'); // active or inactive
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_type', 'status']);
        });
    }
};
