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
        return [
            'form_id' => 'required|exists:forms,id,deleted_at,NULL', // Ensure form_id exists and is not deleted
            'field_name' => 'required|string|max:255', // Validate field_name
            'field_type' => 'required|string', // Validate field_type
            'is_required' => 'required|boolean', // Validate is_required
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
