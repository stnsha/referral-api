<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'priority',
        'consult_call_id',
        'status',
        'status_note',
        'encoded_base'
    ];

    public function referral_details(): HasMany
    {
        return $this->hasMany(ReferralDetails::class, 'referral_id', 'id');
    }

    public function referral_hierarchies(): HasMany
    {
        return $this->hasMany(ReferralHierarchy::class, 'referral_id', 'id');
    }

    public function latest_referral_hierarchy()
    {
        return $this->hasOne(ReferralHierarchy::class)->latestOfMany();
    }

    // Aliases for backward compatibility
    public function referral_histories_old(): HasMany
    {
        return $this->referral_hierarchies();
    }

    public function latest_referral_history_old()
    {
        return $this->latest_referral_hierarchy();
    }

    public function referral_attachments(): HasMany
    {
        return $this->hasMany(ReferralAttachment::class, 'referral_id', 'id');
    }

    /**
     * Get the effective priority for this referral
     * Uses the first create form's priority if available, otherwise uses referral's priority
     */
    public function getEffectivePriority()
    {
        // Get the first hierarchy's create form
        $firstHierarchy = $this->referral_hierarchies()
            ->where('sequence', 1)
            ->first();

        if ($firstHierarchy && $firstHierarchy->referral_create_form) {
            $formPriority = $firstHierarchy->referral_create_form->priority;
            if (!is_null($formPriority)) {
                return $formPriority;
            }
        }

        // Fallback to referral's own priority
        return $this->priority;
    }

    /**
     * Get priority for a specific hierarchy sequence
     */
    public function getPriorityForSequence($sequence)
    {
        $hierarchy = $this->referral_hierarchies()
            ->where('sequence', $sequence)
            ->first();

        // For odd sequences (create forms)
        if ($hierarchy && $hierarchy->sequence % 2 === 1) {
            if ($hierarchy->referral_create_form) {
                $formPriority = $hierarchy->referral_create_form->priority;
                if (!is_null($formPriority)) {
                    return $formPriority;
                }
            }
        }

        // Fallback to referral's priority
        return $this->priority;
    }
}