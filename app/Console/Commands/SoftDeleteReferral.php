<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralCreateForm;
use App\Models\ReferralDetails;
use App\Models\ReferralHierarchy;
use App\Models\ReferralReplyForm;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoftDeleteReferral extends Command
{
    protected $signature = 'referral:soft-delete {--id= : Referral ID to soft delete} {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Soft delete a referral and all its related records (hierarchies, forms, details, attachments)';

    public function handle(): int
    {
        $id = $this->option('id');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($id)) {
            $this->error('Missing required option: --id');
            return self::FAILURE;
        }

        $referral = Referral::find($id);

        if (!$referral) {
            $this->error("Referral ID {$id} not found (or already soft deleted).");
            return self::FAILURE;
        }

        $hierarchies = ReferralHierarchy::where('referral_id', $referral->id)->get();
        $hierarchyIds = $hierarchies->pluck('id');

        $createFormCount = ReferralCreateForm::whereIn('referral_hierarchy_id', $hierarchyIds)->count();
        $replyFormCount = ReferralReplyForm::whereIn('referral_hierarchy_id', $hierarchyIds)->count();
        $detailsCount = ReferralDetails::whereIn('referral_hierarchy_id', $hierarchyIds)->count();
        $attachmentCount = ReferralAttachment::whereIn('referral_hierarchy_id', $hierarchyIds)->count();

        $this->info("Referral ID: {$referral->id}");
        $this->line("Hierarchies: {$hierarchies->count()}");
        $this->line("Create forms: {$createFormCount}");
        $this->line("Reply forms: {$replyFormCount}");
        $this->line("Details: {$detailsCount}");
        $this->line("Attachments: {$attachmentCount}");

        if ($dryRun) {
            $this->warn('Dry run - no records deleted.');
            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            ReferralCreateForm::whereIn('referral_hierarchy_id', $hierarchyIds)->delete();
            ReferralReplyForm::whereIn('referral_hierarchy_id', $hierarchyIds)->delete();
            ReferralDetails::whereIn('referral_hierarchy_id', $hierarchyIds)->delete();
            ReferralAttachment::whereIn('referral_hierarchy_id', $hierarchyIds)->delete();
            ReferralHierarchy::where('referral_id', $referral->id)->delete();
            $referral->delete();

            DB::commit();

            Log::info('Referral soft deleted via command', [
                'referral_id' => $referral->id,
                'hierarchies' => $hierarchies->count(),
                'create_forms' => $createFormCount,
                'reply_forms' => $replyFormCount,
                'details' => $detailsCount,
                'attachments' => $attachmentCount,
            ]);

            $this->info("Referral ID {$referral->id} and related records soft deleted.");
            return self::SUCCESS;
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Failed to soft delete referral', ['referral_id' => $referral->id, 'exception' => $e]);
            $this->error('Failed to soft delete referral: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
