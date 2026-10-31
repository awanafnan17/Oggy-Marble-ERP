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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index('warehouse_id');
            $table->index('product_id');
        });

        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->index('issue_date');
        });

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->index('issue_date');
        });

        // Indexes for stone_to_slab and slab_to_tile are already created.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropIndex(['issue_date']);
        });

        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropIndex(['issue_date']);
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['warehouse_id']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });
    }
};
