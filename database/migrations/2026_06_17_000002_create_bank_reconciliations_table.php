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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->cascadeOnDelete();

            $table->date('statement_date');

            $table->decimal('statement_balance', 15, 4);
            $table->decimal('system_balance', 15, 4);
            $table->decimal('difference', 15, 4);

            $table->boolean('is_closed')->default(false);

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('bank_account_id');
            $table->index('statement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
