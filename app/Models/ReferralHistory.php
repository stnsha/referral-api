<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'staff_id',
        'business_unit_id',
    ];
}
