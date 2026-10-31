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
        // 1. Alter products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type')->default('tile'); // raw_stone, slab, tile, scrap
            $table->string('unit_of_measure')->default('Sq Ft'); // MT, Sq Ft
            $table->string('stone_color')->nullable(); // e.g. Black, Golden, Grey
            $table->string('quality_grade')->nullable(); // Grade A, Grade B, Commercial, Premium
            $table->string('thickness')->nullable(); // e.g. 1.5cm, 2cm
            $table->string('size')->nullable(); // e.g. 60x60, 12x12
        });

        // 2. Alter inventory_batches table
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->string('unit_of_measure')->default('Sq Ft');
            $table->string('product_type')->default('tile');
            $table->string('batch_reference')->nullable();
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->string('quality_grade')->nullable();
        });

        // 3. Alter customer_invoices table
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->decimal('gross_amount', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('delivery_charges', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 4)->default(0);
            $table->decimal('cash_received', 15, 4)->default(0);
            $table->decimal('bank_received', 15, 4)->default(0);
        });

        // 4. Alter supplier_bills table
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->decimal('gross_amount', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('delivery_charges', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 4)->default(0);
            $table->decimal('cash_paid', 15, 4)->default(0);
            $table->decimal('bank_paid', 15, 4)->default(0);
        });

        // 5. Create bank_accounts table
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->string('account_name');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->enum('type', ['bank', 'cash'])->default('bank');
            $table->timestamps();
        });

        // 6. Create bank_transactions table
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'payment', 'receipt']);
            $table->decimal('amount', 15, 4);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 7. Create stone_to_slab_productions table
        Schema::create('stone_to_slab_productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_order_number')->unique();
            $table->foreignId('input_batch_id')->constrained('inventory_batches')->onDelete('restrict');
            $table->decimal('input_quantity', 15, 4); // MT
            $table->foreignId('slab_product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('slabs_produced', 15, 4); // MT
            $table->decimal('wastage_quantity', 15, 4); // MT
            $table->foreignId('scrap_product_id')->nullable()->constrained('products')->onDelete('restrict');
            $table->decimal('scrap_quantity', 15, 4)->default(0);
            $table->string('scrap_unit')->nullable(); // MT or Sq Ft
            $table->decimal('labor_cost', 15, 4)->default(0);
            $table->decimal('electricity_cost', 15, 4)->default(0);
            $table->decimal('fuel_cost', 15, 4)->default(0);
            $table->decimal('maintenance_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('cost_per_mt', 15, 4)->default(0);
            $table->decimal('yield_percentage', 8, 4)->default(0);
            $table->date('production_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('restrict');
            $table->timestamps();
        });

        // 8. Create slab_to_tile_productions table
        Schema::create('slab_to_tile_productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_order_number')->unique();
            $table->foreignId('input_batch_id')->constrained('inventory_batches')->onDelete('restrict');
            $table->decimal('input_quantity', 15, 4); // MT
            $table->foreignId('tile_product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('tiles_produced', 15, 4); // Sq Ft
            $table->decimal('wastage_percentage', 8, 4)->default(0);
            $table->foreignId('scrap_product_id')->nullable()->constrained('products')->onDelete('restrict');
            $table->decimal('scrap_quantity', 15, 4)->default(0);
            $table->string('scrap_unit')->nullable();
            $table->decimal('labor_cost', 15, 4)->default(0);
            $table->decimal('cutting_cost', 15, 4)->default(0);
            $table->decimal('polishing_cost', 15, 4)->default(0);
            $table->decimal('packaging_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('cost_per_sqft', 15, 4)->default(0);
            $table->decimal('conversion_ratio', 15, 4)->default(0); // Sq Ft per MT
            $table->date('production_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('restrict');
            $table->timestamps();
        });

        // 9. Create rental_jobs table
        Schema::create('rental_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('restrict');
            $table->date('job_date');
            $table->string('job_number')->unique();
            $table->decimal('machine_hours', 8, 2)->default(0);
            $table->decimal('machine_rate', 15, 4)->default(0);
            $table->decimal('labor_hours', 8, 2)->default(0);
            $table->decimal('labor_rate', 15, 4)->default(0);
            $table->decimal('other_charges', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('cash_received', 15, 4)->default(0);
            $table->decimal('bank_received', 15, 4)->default(0);
            $table->decimal('remaining_receivable', 15, 4)->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('restrict');
            $table->timestamps();
        });

        // 10. Alter payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });

        Schema::dropIfExists('rental_jobs');
        Schema::dropIfExists('slab_to_tile_productions');
        Schema::dropIfExists('stone_to_slab_productions');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropColumn(['gross_amount', 'discount_amount', 'delivery_charges', 'net_amount', 'cash_paid', 'bank_paid']);
        });

        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropColumn(['gross_amount', 'discount_amount', 'delivery_charges', 'net_amount', 'cash_received', 'bank_received']);
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropColumn(['unit_of_measure', 'product_type', 'batch_reference', 'total_cost', 'quality_grade']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'unit_of_measure', 'stone_color', 'quality_grade', 'thickness', 'size']);
        });
    }
};
