<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralCreateForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_hierarchy_id',
        'referral_reason',
        'referral_condition',
        'medical_history',
        'priority',
    ];

    public function referral_hierarchy(): BelongsTo
    {
        return $this->belongsTo(ReferralHierarchy::class, 'referral_hierarchy_id', 'id');
    }

    /**
     * Get priority with fallback to referral's priority
     */
    public function getEffectivePriority()
    {
        // If this form has a priority, use it
        if (!is_null($this->priority)) {
            return $this->priority;
        }

        // Otherwise, fallback to the referral's priority
        return $this->referral_hierarchy?->referral?->priority;
    }

    /**
     * Get priority as text (Low/Medium/High)
     */
    public function getPriorityTextAttribute()
    {
        $priority = $this->getEffectivePriority();
        return setPriorityReferral($priority);
    }
}
