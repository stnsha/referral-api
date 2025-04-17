<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_token', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->string('use_for');
            $table->timestamps();
        });

        $id = DB::table('api_token')->insertGetId([
            'value' => '',
            'use_for' => 'ODB Api',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        DB::table('api_token')->where('id', $id)->update([
            'value' => "{$id}|4lpr0@r3f3rr4L",
            'updated_at' => Carbon::now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_token');
    }
};
