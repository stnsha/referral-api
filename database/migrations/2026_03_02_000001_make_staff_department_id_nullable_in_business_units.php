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
        Schema::table('business_units', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_department_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Replace NULL values with 0 before restoring NOT NULL constraint.
        DB::table('business_units')->whereNull('staff_department_id')->update(['staff_department_id' => 0]);

        Schema::table('business_units', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_department_id')->nullable(false)->change();
        });
    }
};
