<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalReferee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'organization',
        'position',
        'specialty',
        'address',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}
