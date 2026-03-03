<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormDetailsRequest extends FormRequest
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
        if ($this->isMethod('put')) {
            return [
                'field_name'  => 'sometimes|string|max:255',
                'field_type'  => 'sometimes|string|max:255',
                'is_required' => 'sometimes|boolean',
                'field_value' => 'nullable|string|max:255',
            ];
        }

        return [
            'form_id'                    => 'required|exists:forms,id,deleted_at,NULL',
            'form_details'               => 'required|array|min:1',
            'form_details.*.field_name'  => 'required|string|max:255',
            'form_details.*.field_type'  => 'required|string|max:255',
            'form_details.*.is_required' => 'required|boolean',
            'form_details.*.field_value' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'form_id.required' => 'The form ID is required.',
            'form_id.exists' => 'Form ID does not exist or has been deleted.',
            'field_name.required' => 'Field name is required.',
            'field_name.string' => 'Field name must be a string.',
            'field_name.max' => 'Field name must not exceed 255 characters.',
            'field_type.required' => 'Field type is required.',
            'is_required.required' => 'The required flag is required.',
            'is_required.boolean' => 'The required flag must be a boolean.',
        ];
    }
}
