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
        Schema::create('referral_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_history_id');
            $table->longText('referral_reason');
            $table->longText('referral_condition');
            $table->longText('medical_history')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_details');
    }
};
