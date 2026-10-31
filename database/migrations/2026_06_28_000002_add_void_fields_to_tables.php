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
        $tables = ['customer_invoices', 'supplier_bills', 'payments', 'orders', 'payment_allocations'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->timestamp('voided_at')->nullable();
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->text('void_reason')->nullable();

                $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable();
            $table->string('original_reference_type')->nullable();
            $table->unsignedBigInteger('original_reference_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();

            $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reversal_journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('reversed_by_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['reversal_journal_entry_id']);
            $table->dropForeign(['reversed_by_entry_id']);

            $table->dropColumn([
                'reversal_journal_entry_id',
                'reversed_by_entry_id',
                'original_reference_type',
                'original_reference_id',
                'voided_at',
                'voided_by',
                'void_reason',
            ]);
        });

        $tables = ['customer_invoices', 'supplier_bills', 'payments', 'orders', 'payment_allocations'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropForeign([$t.'_voided_by_foreign']);
                $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
            });
        }
    }
};
