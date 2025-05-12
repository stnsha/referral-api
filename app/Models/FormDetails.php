<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormDetails extends Model
{
    use HasFactory, SoftDeletes;

    // protected $casts = [
    //     'field_value' => 'array',
    // ];

    protected $fillable = [
        'form_id',
        'field_name',
        'field_type',
        'is_required',
        'field_value'
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function referral_details(): HasMany
    {
        return $this->hasMany(ReferralDetails::class, 'form_detail_id', 'id');
    }
}
