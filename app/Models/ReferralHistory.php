<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'is_filled'
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function business_unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }
}
