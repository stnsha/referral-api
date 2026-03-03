<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'label_name',
        'is_hidden',
        'display_on',
    ];

    public function form_details(): HasMany
    {
        return $this->hasMany(FormDetails::class, 'form_id', 'id');
    }

    public function business_units(): BelongsToMany
    {
        return $this->belongsToMany(BusinessUnit::class, 'form_business_units');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(FormCondition::class)->whereNull('deleted_at');
    }
}
