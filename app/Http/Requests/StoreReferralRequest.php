<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_units.assignee.staff_id' => 'required|integer',
            'business_units.assignee.business_unit_id' => 'required|string',
            'business_units.assignee.location' => 'required|string',
            'business_units.assignee.referral_reason' => 'required|string',
            'business_units.assignee.referral_condition' => 'required|string',
            'business_units.assignee.medical_history' => 'nullable|string',
            'business_units.assignee.additional_remarks' => 'nullable|string',

            'business_units.recipient.staff_id' => 'nullable|integer',
            'business_units.recipient.business_unit_id' => 'required|string',
            'business_units.recipient.location' => 'required|string',

            'referral.customer_id' => 'required|integer',
            'referral.priority' => 'required|integer',

            'required_treatment' => 'required|array|min:1',
            'required_treatment.*' => 'integer',

            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.type' => 'required|string',
            'attachments.*.size' => 'required|integer',
            'attachments.*.base64' => 'required|string'
        ];
    }



    public function messages(): array
    {
        return [
            'required_treatment.required' => 'Required treatment is required.',
            'required_treatment.array' => 'Required treatment must be an array.',
            'required_treatment.min' => 'At least one treatment must be selected.',
            'required_treatment.*.integer' => 'Each treatment ID must be a valid integer.',
        ];
    }
}
