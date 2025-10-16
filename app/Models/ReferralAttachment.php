<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_hierarchy_id',
        'file_name',
        'file_type',
        'file_size',
        'encoded_base'
    ];

    public function referralHierarchy(): BelongsTo
    {
        return $this->belongsTo(ReferralHierarchy::class, 'referral_hierarchy_id', 'id');
    }
}
