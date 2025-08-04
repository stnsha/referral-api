<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Referral API",
 *     version="1.0.0",
 *     description="API for managing referrals between business units"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter JWT Bearer token in the format: Bearer {token}"
 * )
 * 
 * @OA\Schema(
 *     schema="BusinessUnit",
 *     type="object",
 *     required={"id", "name", "staff_department_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Emergency Department"),
 *     @OA\Property(property="staff_department_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Referral",
 *     type="object",
 *     required={"id", "ref_id", "customer_id", "business_unit_id", "priority", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="ref_id", type="string", example="#REF0001"),
 *     @OA\Property(property="customer_id", type="integer", example=10),
 *     @OA\Property(property="business_unit_id", type="integer", example=6),
 *     @OA\Property(property="referral_reason", type="string", example="Vestibular-Related Balance Issue"),
 *     @OA\Property(property="referral_condition", type="string", example="Patient reports persistent dizziness..."),
 *     @OA\Property(property="medical_history", type="string", example="Mild scoliosis diagnosed during teenage years."),
 *     @OA\Property(property="priority", type="integer", example=2),
 *     @OA\Property(property="status", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Form",
 *     type="object",
 *     required={"form_id", "label_name", "business_unit_id"},
 *     @OA\Property(property="form_id", type="integer", example=12),  
 *     @OA\Property(property="label_name", type="string", example="Assessment Form"),
 *     @OA\Property(property="is_hidden", type="boolean", example=false),
 *     @OA\Property(property="business_unit_id", type="integer", example=3),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="form_details", 
 *         type="array", 
 *         @OA\Items(ref="#/components/schemas/FormDetail")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="FormDetail",
 *     type="object", 
 *     required={"form_detail_id", "field_name", "field_type", "is_required"},
 *     @OA\Property(property="form_detail_id", type="integer", example=101),
 *     @OA\Property(property="field_name", type="string", example="Blood Pressure"),
 *     @OA\Property(property="field_type", type="string", example="text"),
 *     @OA\Property(property="is_required", type="boolean", example=true),
 *     @OA\Property(
 *         property="field_value",
 *         oneOf={
 *             @OA\Schema(type="string", example="120/80"),
 *             @OA\Schema(
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="form_detail_id", type="integer", example=201),
 *                     @OA\Property(property="field_value", type="string", example="Option A")
 *                 )
 *             )
 *         }  
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
