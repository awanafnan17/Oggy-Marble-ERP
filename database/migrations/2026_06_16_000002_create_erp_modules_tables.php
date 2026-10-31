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
        // 1. INVENTORY MODULE
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('pcs'); // pcs, kg, ltrs, etc.
            $table->decimal('cost', 15, 4)->default(0);
            $table->decimal('price', 15, 4)->default(0);
            $table->timestamps();
        });

        // 2. CONTACTS & ORDERS MODULE (Sales & Purchases)
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['customer', 'vendor', 'both']);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('restrict');
            $table->enum('type', ['sales', 'purchase']);
            $table->string('order_number')->unique();
            $table->string('status')->default('draft'); // draft, confirmed, completed, cancelled
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->date('order_date');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 4);
            $table->timestamps();
        });

        // 3. INVENTORY TRANSACTIONS LEDGER
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->decimal('quantity', 15, 4); // positive for IN, negative for OUT
            $table->decimal('unit_cost', 15, 4);
            $table->enum('type', ['IN', 'OUT']);
            $table->string('reference_type')->nullable(); // purchase, sale, production, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. ACCOUNTING MODULE
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->decimal('balance', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('date');
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('restrict');
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->timestamps();
        });

        // 5. PRODUCTION MODULE (BOM & Work Orders)
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict'); // finished product
            $table->string('name');
            $table->decimal('labor_cost_estimate', 15, 4)->default(0);
            $table->decimal('overhead_cost_estimate', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict'); // raw material
            $table->decimal('quantity', 15, 4); // raw material quantity needed for 1 unit of BOM finished product
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->onDelete('restrict');
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->decimal('quantity', 15, 4); // finished product units to produce
            $table->decimal('actual_material_cost', 15, 4)->default(0);
            $table->decimal('actual_labor_cost', 15, 4)->default(0);
            $table->decimal('actual_overhead_cost', 15, 4)->default(0);
            $table->date('start_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
        Schema::dropIfExists('journal_items');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('stock_ledgers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warehouses');
    }
};
