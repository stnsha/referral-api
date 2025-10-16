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
        Schema::create('referral_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('external_referee_id')->nullable();
            $table->unsignedBigInteger('external_organization_id')->nullable();
            $table->integer('sequence');
            $table->longText('additional_remarks')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_filled')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('external_referee_id')->references('id')->on('external_referees')->onDelete('cascade');
            $table->foreign('external_organization_id')->references('id')->on('external_organizations')->onDelete('cascade');
            $table->index(['referral_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_hierarchies');
    }
};
