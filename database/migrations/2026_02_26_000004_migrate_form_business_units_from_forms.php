<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $forms = DB::table('forms')
            ->whereNull('deleted_at')
            ->whereNotNull('business_unit_id')
            ->select('id', 'business_unit_id')
            ->get();

        foreach ($forms as $form) {
            DB::table('form_business_units')->insertOrIgnore([
                'form_id' => $form->id,
                'business_unit_id' => $form->business_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('form_business_units')->truncate();
    }
};
