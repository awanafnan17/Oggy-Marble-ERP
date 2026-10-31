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
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->boolean('is_reconciled')->default(false);
            $table->foreignId('bank_reconciliation_id')
                ->nullable()
                ->constrained('bank_reconciliations')
                ->nullOnDelete();

            $table->index('is_reconciled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_reconciliation_id']);
            $table->dropIndex(['is_reconciled']);
            $table->dropColumn(['is_reconciled', 'bank_reconciliation_id']);
        });
    }
};
