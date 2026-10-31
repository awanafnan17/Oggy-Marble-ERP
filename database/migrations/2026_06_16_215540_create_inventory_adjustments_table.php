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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_batch_id')
                ->constrained('inventory_batches')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products');

            $table->foreignId('warehouse_id')
                ->constrained('warehouses');

            $table->decimal('system_quantity', 15, 4);
            $table->decimal('physical_quantity', 15, 4);
            $table->decimal('difference', 15, 4);

            $table->string('reason')->nullable();
            $table->date('adjustment_date');

            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('inventory_batch_id');
            $table->index('adjustment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
