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
        Schema::create('referral_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('business_unit_id'); //refer to business unit id on referral, not department_id (FK)
            $table->unsignedBigInteger('location');
            $table->integer('sequence');
            $table->longText('reason');
            $table->longText('condition');
            $table->longText('medical_history')->nullable();
            $table->longText('additional_remarks')->nullable();
            $table->boolean('is_filled')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->index(['referral_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_histories');
    }
};
