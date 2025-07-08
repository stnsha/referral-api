<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'staff_id',
        'business_unit_id',
        'location',
        'sequence',
        'referral_reason',
        'referral_condition',
        'medical_history',
        'additional_remarks',
        'is_filled',
        'external_referee_id'
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function business_unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function referral_details(): HasMany
    {
        return $this->hasMany(ReferralDetails::class, 'referral_history_id', 'id');
    }

    public function referral_attachments(): HasMany
    {
        return $this->hasMany(ReferralAttachment::class, 'referral_history_id', 'id');
    }
}
