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
        $rules = [
            'business_units.assignee.staff_id' => 'required|integer',
            'business_units.assignee.business_unit_id' => 'required|string',
            'business_units.assignee.location' => 'required|string',
            'business_units.assignee.referral_reason' => 'required|string',
            'business_units.assignee.referral_condition' => 'required|string',
            'business_units.assignee.medical_history' => 'nullable|string',
            'business_units.assignee.additional_remarks' => 'nullable|string',

            'referral.customer_id' => 'required|integer',
            'referral.priority' => 'required|integer',

            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.type' => 'required|string',
            'attachments.*.size' => 'required|integer',
            'attachments.*.base64' => 'required|string'
        ];

        // Check if this is an external referral
        $isExternalReferral = $this->has('business_units.recipient.referee') ||
                             $this->has('business_units.recipient.organization');

        if ($isExternalReferral) {
            // External referral validation
            $rules['business_units.recipient.organization'] = 'required|integer';
            $rules['business_units.recipient.location_organization'] = 'nullable|string';
            $rules['business_units.recipient.referee'] = 'required|string';
            $rules['required_treatment'] = 'nullable|array';
        } else {
            // Internal referral validation
            $rules['business_units.recipient.staff_id'] = 'nullable|integer';
            $rules['business_units.recipient.business_unit_id'] = 'required|string';
            $rules['business_units.recipient.location'] = 'required|string';
            $rules['required_treatment'] = 'required|array|min:1';
        }

        $rules['required_treatment.*'] = 'integer';

        return $rules;
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