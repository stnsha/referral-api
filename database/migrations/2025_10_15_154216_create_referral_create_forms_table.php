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
        Schema::create('referral_create_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_hierarchy_id');
            $table->longText('referral_reason')->nullable();
            $table->longText('referral_condition')->nullable();
            $table->longText('medical_history')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('referral_hierarchy_id')->references('id')->on('referral_hierarchies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_create_forms');
    }
};
