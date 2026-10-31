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
        Schema::create('production_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_number')->unique();
            $table->string('production_order_reference')->nullable();
            $table->string('production_type');
            $table->string('machine');
            $table->dateTime('planned_start_date');
            $table->dateTime('planned_end_date');
            $table->string('priority');
            $table->foreignId('planned_input_product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('planned_input_batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->decimal('planned_input_quantity', 15, 4);
            $table->decimal('expected_output_quantity', 15, 4);
            $table->decimal('expected_wastage_quantity', 15, 4)->default(0);
            $table->decimal('expected_wastage_percentage', 8, 4)->default(0);
            $table->string('assigned_operator')->nullable();
            $table->string('status')->default('planned');
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('production_type');
            $table->index('machine');
            $table->index('status');
            $table->index('planned_start_date');
            $table->index('planned_end_date');
        });

        Schema::table('stone_to_slab_productions', function (Blueprint $table) {
            $table->foreignId('production_schedule_id')->nullable()->constrained('production_schedules')->onDelete('set null');
            $table->decimal('wastage_percentage', 8, 4)->default(0);
            $table->string('waste_reason')->nullable();
            $table->string('waste_operator_name')->nullable();
        });

        Schema::table('slab_to_tile_productions', function (Blueprint $table) {
            $table->foreignId('production_schedule_id')->nullable()->constrained('production_schedules')->onDelete('set null');
            $table->decimal('wastage_quantity', 15, 4)->default(0);
            $table->string('waste_reason')->nullable();
            $table->string('waste_operator_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slab_to_tile_productions', function (Blueprint $table) {
            $table->dropForeign(['production_schedule_id']);
            $table->dropColumn(['production_schedule_id', 'wastage_quantity', 'waste_reason', 'waste_operator_name']);
        });

        Schema::table('stone_to_slab_productions', function (Blueprint $table) {
            $table->dropForeign(['production_schedule_id']);
            $table->dropColumn(['production_schedule_id', 'wastage_percentage', 'waste_reason', 'waste_operator_name']);
        });

        Schema::dropIfExists('production_schedules');
    }
};
