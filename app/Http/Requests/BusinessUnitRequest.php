<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'staff_department_id' => 'required|integer',
            'is_active' => 'nullable|boolean',
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
            'name.required' => 'Business unit name is required.',
            'name.string' => 'Business unit name must be a string.',
            'name.max' => 'Business unit name cannot exceed 255 characters.',
            'staff_department_id.required' => 'Staff department ID is required.',
            'staff_department_id.integer' => 'Staff department ID must be an integer.',
            'is_active.boolean' => 'Is active must be a boolean value.',
        ];
    }
}
