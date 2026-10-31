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
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('authorized_by_name')->nullable();
            $table->text('remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->dropColumn(['vehicle_number', 'driver_name', 'receiver_name', 'authorized_by_name', 'remarks']);
        });
    }
};
