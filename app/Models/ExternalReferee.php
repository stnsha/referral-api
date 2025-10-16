<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalReferee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_organization_id',
        'name',
        'email',
        'phone',
        'position',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(ExternalOrganization::class, 'external_organization_id', 'id');
    }

    public function referral_histories(): HasMany
    {
        return $this->hasMany(ReferralHistory::class, 'external_referee_id', 'id');
    }
}