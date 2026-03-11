<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assign stationery (combined letterhead + footer) image paths to business units.
     *
     * These are full A4 page stationery images stored in public/logo/.
     * They are used as a background layer in the generated referral PDF.
     */
    public function up(): void
    {
        DB::table('business_units')
            ->where('id', 3)
            ->update(['letterhead' => 'logo/Letterhead Alpro Clinic.jpg']);

        DB::table('business_units')
            ->where('id', 4)
            ->update(['letterhead' => 'logo/Letterhead Alpro Optisaver.jpg.jpeg']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('business_units')
            ->whereIn('id', [3, 4])
            ->update(['letterhead' => null]);
    }
};
