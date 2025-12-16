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
        Schema::table('referral_hierarchies', function (Blueprint $table) {
            if (Schema::hasColumn('referral_hierarchies', 'encoded_base')) {
                $table->dropColumn('encoded_base');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_hierarchies', function (Blueprint $table) {
            $table->longText('encoded_base')->nullable()->after('is_filled');
        });
    }
};
