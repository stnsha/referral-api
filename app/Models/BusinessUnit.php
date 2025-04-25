<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Info(
 *     version="1.0",
 *     title="Referral API",
 *     description="Backend API for referral",
 *     @OA\Contact(name="Digital Innovation")
 * )
 * @OA\Server(
 *     url="http://172.18.28.51:8002",
 *     description="MyHealth server"
 * )
 *
 */
//url="http://127.0.0.1:8000",
class BusinessUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'staff_department_id',
        'is_active',
    ];
}
