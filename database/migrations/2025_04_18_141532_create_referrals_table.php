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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('business_unit_id');
            $table->longText('reason');
            $table->longText('condition');
            $table->longText('medical_history')->nullable();
            $table->integer('priority');
            $table->integer('status')->default(1); //open
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->index(['customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
