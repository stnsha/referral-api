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
        Schema::create('referral_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_history_id');
            $table->string('file_name');
            $table->string('file_type');
            $table->string('file_size');
            $table->longText('encoded_base');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('referral_history_id')->references('id')->on('referral_histories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_attachments');
    }
};
