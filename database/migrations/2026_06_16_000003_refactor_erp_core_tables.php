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
        // 1. Drop balance column from accounts (balances will be ledger-derived)
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('balance');
        });

        // 2. Create inventory_batches table
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->string('reference_type'); // purchase, production, adjustment
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_remaining', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->dateTime('received_at');
            $table->timestamps();
        });

        // 3. Add batch_id to stock_ledgers
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->onDelete('restrict');
        });

        // 4. Create customer_invoices table
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('contact_id')->constrained()->onDelete('restrict');
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 15, 4);
            $table->decimal('remaining_balance', 15, 4);
            $table->string('status')->default('unpaid'); // unpaid, partial, paid
            $table->timestamps();
        });

        // 5. Create supplier_bills table
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('contact_id')->constrained()->onDelete('restrict');
            $table->string('bill_number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 15, 4);
            $table->decimal('remaining_balance', 15, 4);
            $table->string('status')->default('unpaid'); // unpaid, partial, paid
            $table->timestamps();
        });

        // 6. Create payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('contact_id')->constrained()->onDelete('restrict');
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->enum('type', ['receipt', 'payment']); // receipt = customer inward, payment = supplier outward
            $table->string('payment_method');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Create payment_allocations table
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('allocatable_type'); // CustomerInvoice or SupplierBill class
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->index(['allocatable_type', 'allocatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('customer_invoices');

        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::dropIfExists('inventory_batches');

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('balance', 15, 4)->default(0);
        });
    }
};
