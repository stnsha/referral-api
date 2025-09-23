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
        'referral_history_id',
        'file_name',
        'file_type',
        'file_size',
        'encoded_base'
    ];

    public function referralHistory(): BelongsTo
    {
        return $this->belongsTo(ReferralHistory::class, 'referral_history_id', 'id');
    }
}
