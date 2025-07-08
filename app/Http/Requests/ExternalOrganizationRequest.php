<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalOrganizationRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
            'name.string' => 'Organization name must be a string.',
            'name.max' => 'Organization name cannot exceed 255 characters.',

            'address.string' => 'Organization address must be a string.',
            'address.max' => 'Organization address cannot exceed 255 characters.',

            'postcode.string' => 'Organization postcode must be a string.',
            'postcode.max' => 'Organization postcode cannot exceed 20 characters.',

            'state.string' => 'Organization state must be a string.',
            'state.max' => 'Organization state cannot exceed 255 characters.',

            'country.string' => 'Organization country must be a string.',
            'country.max' => 'Organization country cannot exceed 255 characters.',
        ];
    }
}
