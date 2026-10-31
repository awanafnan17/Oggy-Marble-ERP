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
            if (! Schema::hasColumn('bank_transactions', 'proof_image_path')) {
                $table->string('proof_image_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('bank_transactions', 'proof_image_size')) {
                $table->unsignedInteger('proof_image_size')->nullable()->after('proof_image_path');
            }
            if (! Schema::hasColumn('bank_transactions', 'proof_image_mime')) {
                $table->string('proof_image_mime', 50)->nullable()->after('proof_image_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('bank_transactions', 'proof_image_path')) {
                $columns[] = 'proof_image_path';
            }
            if (Schema::hasColumn('bank_transactions', 'proof_image_size')) {
                $columns[] = 'proof_image_size';
            }
            if (Schema::hasColumn('bank_transactions', 'proof_image_mime')) {
                $columns[] = 'proof_image_mime';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
