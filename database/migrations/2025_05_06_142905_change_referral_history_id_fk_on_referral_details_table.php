<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        try {
            Schema::table('referral_details', function (Blueprint $table) {
                $table->dropForeign(['referral_history_id']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to drop foreign key from referral_details: ' . $e->getMessage());
        }

        DB::statement('ALTER TABLE referral_details CHANGE referral_history_id referral_id BIGINT UNSIGNED');

        Schema::table('referral_details', function (Blueprint $table) {
            $table->foreign('referral_id')
                ->references('id')
                ->on('referrals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('referral_details', function (Blueprint $table) {
                $table->dropForeign(['referral_id']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to drop foreign key from referral_details: ' . $e->getMessage());
        }

        DB::statement('ALTER TABLE referral_details CHANGE referral_id referral_history_id BIGINT UNSIGNED');

        Schema::table('referral_details', function (Blueprint $table) {
            $table->foreign('referral_history_id')
                ->references('id')
                ->on('referral_histories')
                ->onDelete('cascade');
        });
    }
};
