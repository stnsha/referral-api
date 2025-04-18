<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_history_id',
        'form_detail_id',
        'value',
    ];
}
