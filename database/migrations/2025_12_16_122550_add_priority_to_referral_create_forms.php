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
        Schema::table('referral_create_forms', function (Blueprint $table) {
            $table->integer('priority')->nullable()->after('medical_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_create_forms', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
