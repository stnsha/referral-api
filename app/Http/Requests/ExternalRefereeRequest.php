<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalRefereeRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'external_organization_id' => 'nullable|exists:external_organizations,id',
            'organization.name' => 'string|max:255',
            'organization.address' => 'nullable|string|max:255',
            'organization.postcode' => 'nullable|string|max:10',
            'organization.state' => 'nullable|string|max:255',
            'organization.country' => 'nullable|string|max:255'
        ];

        if ($this->isMethod('POST')) {
            $rules['name'] = 'required|' . $rules['name'];
            $rules['email'] = 'nullable|email|unique:external_referees,email';

            // Require either external_organization_id or all organization fields
            if (!$this->has('external_organization_id')) {
                $rules['organization.name'] = 'required|string|max:255';
                $rules['organization.address'] = 'nullable|string|max:255';
                $rules['organization.postcode'] = 'nullable|string|max:10';
                $rules['organization.state'] = 'nullable|string|max:255';
                $rules['organization.country'] = 'nullable|string|max:255';
            }
        }

        // Add unique validation for update requests, excluding the current record
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['email'] = 'nullable|email|unique:external_referees,email,' . $this->route('externalReferee')->id;

            // If external_organization_id is provided, validate it exists
            if ($this->has('external_organization_id')) {
                $rules['external_organization_id'] = 'required|exists:external_organizations,id';
            } elseif ($this->hasAny(['organization.name', 'organization.address', 'organization.postcode', 'organization.state', 'organization.country'])) {
                // If any organization field is provided, require all fields
                $rules['organization.name'] = 'required|string|max:255';
                $rules['organization.address'] = 'nullable|string|max:255';
                $rules['organization.postcode'] = 'nullable|string|max:10';
                $rules['organization.state'] = 'nullable|string|max:255';
                $rules['organization.country'] = 'nullable|string|max:255';
            }
        }

        return $rules;
    }
}
