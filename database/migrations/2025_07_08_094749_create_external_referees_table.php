<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_referees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_organization_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('specialty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('external_organization_id')->references('id')->on('external_organizations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_referees');
    }
};
