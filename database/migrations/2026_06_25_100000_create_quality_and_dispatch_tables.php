<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Quality Inspections
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('qc_number')->unique();
            $table->date('inspection_date');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->foreignId('production_schedule_id')->nullable()->constrained('production_schedules')->onDelete('set null');
            $table->string('inspection_type'); // production_qc, pre_dispatch_qc, return_qc, stock_qc
            $table->string('grade'); // A, B, C, reject
            $table->string('defect_type')->nullable(); // cracks, size_variation, color_variation, polish_issue, thickness_issue, breakage, edge_damage, other
            $table->decimal('checked_quantity', 15, 4);
            $table->decimal('rejected_quantity', 15, 4);
            $table->decimal('accepted_quantity', 15, 4);
            $table->string('inspector_name');
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft'); // draft, passed, failed, partially_passed
            $table->timestamps();

            // Indexes for performance
            $table->index('inspection_date');
            $table->index('grade');
            $table->index('status');
        });

        // 2. Delivery Challans
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->string('challan_number')->unique();
            $table->dateTime('challan_date');
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null'); // sales order
            $table->string('vehicle_number');
            $table->string('driver_name');
            $table->string('receiver_name')->nullable();
            $table->string('prepared_by');
            $table->string('authorized_by')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending'); // pending, packed, dispatched, delivered, returned, cancelled
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('challan_date');
            $table->index('status');
        });

        // 3. Delivery Challan Items (Multiple items per challan)
        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_challan_id')->constrained('delivery_challans')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->decimal('quantity', 15, 4);
            $table->string('unit');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
        Schema::dropIfExists('quality_inspections');
    }
};
