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
        'status',
        'status_note',
        'encoded_base'
    ];

    /**
     * Status
     * 1 = Open
     * 2 = In Progress
     * 3 = Forwarded
     * 4 = Closed
     */

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
}