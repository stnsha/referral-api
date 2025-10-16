<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralReplyForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_hierarchy_id',
        'post_diagnosis',
        'feedback',
        'outcome',
    ];

    public function referral_hierarchy(): BelongsTo
    {
        return $this->belongsTo(ReferralHierarchy::class, 'referral_hierarchy_id', 'id');
    }
}
