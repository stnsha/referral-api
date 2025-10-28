<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalOrganization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'postcode',
        'state',
        'country',
        'phone',
    ];

    public function referees(): HasMany
    {
        return $this->hasMany(ExternalReferee::class, 'external_organization_id', 'id');
    }
}