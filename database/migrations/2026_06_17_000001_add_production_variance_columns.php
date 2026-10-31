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
        // Extend stone_to_slab_productions
        Schema::table('stone_to_slab_productions', function (Blueprint $table) {
            $table->decimal('expected_material_cost', 15, 4)->nullable();
            $table->decimal('expected_labor_cost', 15, 4)->nullable();
            $table->decimal('expected_overhead_cost', 15, 4)->nullable();
            $table->decimal('expected_total_cost', 15, 4)->nullable();

            $table->decimal('variance_amount', 15, 4)->nullable();
            $table->decimal('variance_percentage', 10, 4)->nullable();
            $table->boolean('is_favorable')->nullable();

            $table->foreignId('variance_journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->index('production_date');
        });

        // Extend slab_to_tile_productions
        Schema::table('slab_to_tile_productions', function (Blueprint $table) {
            $table->decimal('expected_material_cost', 15, 4)->nullable();
            $table->decimal('expected_labor_cost', 15, 4)->nullable();
            $table->decimal('expected_overhead_cost', 15, 4)->nullable();
            $table->decimal('expected_total_cost', 15, 4)->nullable();

            $table->decimal('variance_amount', 15, 4)->nullable();
            $table->decimal('variance_percentage', 10, 4)->nullable();
            $table->boolean('is_favorable')->nullable();

            $table->foreignId('variance_journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->index('production_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stone_to_slab_productions', function (Blueprint $table) {
            $table->dropForeign(['variance_journal_entry_id']);
            $table->dropIndex(['production_date']);
            $table->dropColumn([
                'expected_material_cost',
                'expected_labor_cost',
                'expected_overhead_cost',
                'expected_total_cost',
                'variance_amount',
                'variance_percentage',
                'is_favorable',
                'variance_journal_entry_id',
            ]);
        });

        Schema::table('slab_to_tile_productions', function (Blueprint $table) {
            $table->dropForeign(['variance_journal_entry_id']);
            $table->dropIndex(['production_date']);
            $table->dropColumn([
                'expected_material_cost',
                'expected_labor_cost',
                'expected_overhead_cost',
                'expected_total_cost',
                'variance_amount',
                'variance_percentage',
                'is_favorable',
                'variance_journal_entry_id',
            ]);
        });
    }
};
