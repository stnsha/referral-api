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
    ];

    public function referral_hierarchy(): BelongsTo
    {
        return $this->belongsTo(ReferralHierarchy::class, 'referral_hierarchy_id', 'id');
    }
}
