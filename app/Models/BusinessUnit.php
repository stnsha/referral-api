<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Server(
 *      url="http://127.0.0.1:8000",
 *      description="Local Server"
 * )
 * 
 * @OA\Server(
 *      url="http://mytotalhealth.com.my/referral-api",
 *      description="MyHealth Server (Staging)"
 * )
 */

class BusinessUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'staff_department_id',
        'outlet_id',
        'is_active',
    ];

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_business_units');
    }
}