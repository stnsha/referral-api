<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'file_name',
        'file_type',
        'file_size',
        'encoded_base'
    ];
}
