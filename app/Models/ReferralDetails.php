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
        'referral_history_id',
        'form_id',
        'value',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function referral_histories(): BelongsTo
    {
        return $this->belongsTo(ReferralDetails::class, 'referral_history_id', 'id');
    }
}
