<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralHierarchy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'staff_id',
        'business_unit_id',
        'location',
        'external_referee_id',
        'external_organization_id',
        'sequence',
        'additional_remarks',
        'is_read',
        'is_filled',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_filled' => 'boolean',
    ];

    // Helper methods to determine form type based on sequence
    public function isCreateForm(): bool
    {
        return $this->sequence % 2 === 1;
    }

    // Helper methods to determine actor type
    public function isInternal(): bool
    {
        return !is_null($this->staff_id) && !is_null($this->business_unit_id);
    }

    public function isExternal(): bool
    {
        return !is_null($this->external_referee_id);
    }

    // Relationships
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function business_unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function external_referee(): BelongsTo
    {
        return $this->belongsTo(ExternalReferee::class, 'external_referee_id', 'id');
    }

    public function external_organization(): BelongsTo
    {
        return $this->belongsTo(ExternalOrganization::class, 'external_organization_id', 'id');
    }

    public function referral_create_form(): HasOne
    {
        return $this->hasOne(ReferralCreateForm::class, 'referral_hierarchy_id', 'id');
    }

    public function referral_reply_form(): HasOne
    {
        return $this->hasOne(ReferralReplyForm::class, 'referral_hierarchy_id', 'id');
    }

    public function referral_details(): HasMany
    {
        return $this->hasMany(ReferralDetails::class, 'referral_hierarchy_id', 'id');
    }

    public function referral_attachments(): HasMany
    {
        return $this->hasMany(ReferralAttachment::class, 'referral_hierarchy_id', 'id');
    }
}