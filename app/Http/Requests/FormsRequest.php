<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormsRequest extends FormRequest
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
            'business_unit_id' => 'required|exists:business_units,id',
            'label_name' => 'required|string|max:255',
            'is_hidden' => 'required|boolean',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'business_unit_id.required' => 'Business unit ID is required.',
            'business_unit_id.exists' => 'Selected business unit ID does not exist.',
            'label_name.required' => 'Label name is required.',
            'label_name.string' => 'Label name must be a string.',
            'label_name.max' => 'Label name may not be greater than 255 characters.',
            'is_hidden.required' => 'is_hidden is required.',
        ];
    }
}
