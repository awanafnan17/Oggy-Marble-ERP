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
        // 1. Add indexes to inventory_batches
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index(['product_id', 'quantity_remaining']);
            $table->index('received_at');
        });

        // 2. Add dynamic account relationships to products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['inventory_account_id']);
            $table->dropForeign(['cogs_account_id']);
            $table->dropForeign(['revenue_account_id']);
            $table->dropColumn(['inventory_account_id', 'cogs_account_id', 'revenue_account_id']);
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'quantity_remaining']);
            $table->dropIndex(['received_at']);
        });
    }
};
