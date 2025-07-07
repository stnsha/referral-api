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
            'email' => 'email',
            'phone' => 'nullable|string|max:20',
            'organization' => 'string|max:255',
            'position' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'address' => 'nullable|string'
        ];

        // Add required validation for store requests
        if ($this->isMethod('POST')) {
            $rules['name'] = 'required|' . $rules['name'];
            $rules['email'] = 'required|' . $rules['email'] . '|unique:external_referees,email';
            $rules['organization'] = 'required|' . $rules['organization'];
        }

        // Add unique validation for update requests, excluding the current record
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['email'] .= '|unique:external_referees,email,' . $this->route('externalReferee')->id;
        }

        return $rules;
    }
}
