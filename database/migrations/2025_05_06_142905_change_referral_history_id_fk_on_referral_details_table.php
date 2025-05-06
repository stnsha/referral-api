<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referral_details', function (Blueprint $table) {
            $table->dropForeign(['referral_history_id']);

            $table->renameColumn('referral_history_id', 'referral_id');
        });

        Schema::table('referral_details', function (Blueprint $table) {
            $table->foreign('referral_id')
                ->references('id')
                ->on('referrals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('referral_details', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
        });

        Schema::table('referral_details', function (Blueprint $table) {
            $table->renameColumn('referral_id', 'referral_history_id');
        });

        Schema::table('referral_details', function (Blueprint $table) {
            $table->foreign('referral_history_id')
                ->references('id')
                ->on('referral_histories')
                ->onDelete('cascade');
        });
    }
};
