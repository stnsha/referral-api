<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Get all referrals with their priority
            $referrals = DB::table('referrals')
                ->select('id', 'priority')
                ->whereNull('deleted_at')
                ->get();

            $updated = 0;
            $skipped = 0;

            foreach ($referrals as $referral) {
                // Find the first hierarchy (sequence = 1) for this referral
                $firstHierarchy = DB::table('referral_hierarchies')
                    ->where('referral_id', $referral->id)
                    ->where('sequence', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if ($firstHierarchy) {
                    // Update the create form for this hierarchy with the referral's priority
                    $affectedRows = DB::table('referral_create_forms')
                        ->where('referral_hierarchy_id', $firstHierarchy->id)
                        ->whereNull('deleted_at')
                        ->update(['priority' => $referral->priority]);

                    if ($affectedRows > 0) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }

            Log::info('Priority migration completed', [
                'total_referrals' => $referrals->count(),
                'updated_create_forms' => $updated,
                'skipped' => $skipped,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Set all priorities in referral_create_forms to null
            DB::table('referral_create_forms')
                ->whereNull('deleted_at')
                ->update(['priority' => null]);

            Log::info('Priority migration rolled back - all priorities in referral_create_forms set to null');
        });
    }
};
