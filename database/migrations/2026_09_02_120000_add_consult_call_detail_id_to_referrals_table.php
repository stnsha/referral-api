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
        Schema::table('referrals', function (Blueprint $table) {
            // Denormalised pointer to the specific consultation a referral came
            // out of. consult_call_id stays as the call-level pointer; this is
            // populated only when the caller knows the detail (referrals launched
            // from the consult-call app). Nullable: the manual referral entry
            // path only collects a consult call id.
            $table->unsignedBigInteger('consult_call_detail_id')
                ->nullable()
                ->after('consult_call_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('consult_call_detail_id');
        });
    }
};
