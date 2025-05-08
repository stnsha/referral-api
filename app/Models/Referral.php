<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'reason',
        'condition',
        'medical_history',
        'priority',
    ];

    public function referral_details(): HasMany
    {
        return $this->hasMany(ReferralDetails::class, 'referral_id', 'id');
    }

    public function referral_histories(): HasMany
    {
        return $this->hasMany(ReferralHistory::class, 'referral_id', 'id');
    }
}
