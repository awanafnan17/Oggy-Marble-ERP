<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('machine_code')->unique();
            $table->string('name')->unique();
            $table->string('type'); // cutter, polishing, crane, loader, other
            $table->string('capacity_note')->nullable();
            $table->string('operator_name')->nullable();
            $table->string('status')->default('active'); // active, inactive, maintenance
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // Seed default predefined machines
        $defaults = [
            [
                'machine_code' => 'MC-GSAW-01',
                'name' => 'Gang Saw 1',
                'type' => 'cutter',
                'capacity_note' => 'High capacity block slab slicer',
                'operator_name' => 'Ali Khan',
                'status' => 'active',
                'remarks' => 'Primary factory marble block gang saw cutter.',
            ],
            [
                'machine_code' => 'MC-GSAW-02',
                'name' => 'Gang Saw 2',
                'type' => 'cutter',
                'capacity_note' => 'Standard capacity block slab slicer',
                'operator_name' => 'Zubair Ahmed',
                'status' => 'active',
                'remarks' => 'Backup slab gang saw cutter.',
            ],
            [
                'machine_code' => 'MC-BCUT-01',
                'name' => 'Block Cutter A',
                'type' => 'cutter',
                'capacity_note' => 'Medium block cutter',
                'operator_name' => 'Muhammad Bilal',
                'status' => 'active',
                'remarks' => 'Heavy duty single blade block cutter.',
            ],
            [
                'machine_code' => 'MC-BCUT-02',
                'name' => 'Block Cutter B',
                'type' => 'cutter',
                'capacity_note' => 'Medium block cutter',
                'operator_name' => 'Tariq Mahmood',
                'status' => 'active',
                'remarks' => 'Auxiliary single blade block cutter.',
            ],
            [
                'machine_code' => 'MC-POL-01',
                'name' => 'Polishing Line 1',
                'type' => 'polishing',
                'capacity_note' => 'Automatic continuous slab polisher',
                'operator_name' => 'Sajid Ali',
                'status' => 'active',
                'remarks' => '16-head continuous line polisher.',
            ],
            [
                'machine_code' => 'MC-ECUT-01',
                'name' => 'Edge Cutter A',
                'type' => 'cutter',
                'capacity_note' => 'Cross-cut sizing machine',
                'operator_name' => 'Imran Khan',
                'status' => 'active',
                'remarks' => 'Used for sizing slabs to finished tiles.',
            ],
        ];

        foreach ($defaults as $m) {
            DB::table('machines')->insert(array_merge($m, [
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
