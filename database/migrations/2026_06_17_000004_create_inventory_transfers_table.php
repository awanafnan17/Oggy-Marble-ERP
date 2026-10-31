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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_batch_id')
                ->constrained('inventory_batches')
                ->cascadeOnDelete();

            $table->foreignId('destination_batch_id')
                ->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->constrained('products');

            $table->foreignId('from_warehouse_id')
                ->constrained('warehouses');

            $table->foreignId('to_warehouse_id')
                ->constrained('warehouses');

            $table->decimal('quantity', 15, 4);
            $table->date('transfer_date');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('product_id');
            $table->index('transfer_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
