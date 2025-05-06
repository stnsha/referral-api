<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'label_name',
        'is_hidden'
    ];

    public function form_details(): HasMany
    {
        return $this->hasMany(FormDetails::class, 'form_id', 'id');
    }

    public function business_unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }
}
