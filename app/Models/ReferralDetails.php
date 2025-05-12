<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'form_detail_id',
        'value',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function form_detail(): BelongsTo
    {
        return $this->belongsTo(FormDetails::class, 'form_detail_id', 'id');
    }
}
