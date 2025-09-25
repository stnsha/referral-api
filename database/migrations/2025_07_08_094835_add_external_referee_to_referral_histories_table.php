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
        Schema::table('referral_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('external_referee_id')->nullable()->after('additional_remarks');
            $table->foreign('external_referee_id')->references('id')->on('external_referees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_histories', function (Blueprint $table) {
            $table->dropForeign(['external_referee_id']);
            $table->dropColumn('external_referee_id');
        });
    }
};