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
            'business_unit_ids' => $this->isMethod('post') ? 'required|array|min:1' : 'nullable|array',
            'business_unit_ids.*' => 'exists:business_units,id',
            'label_name' => 'required|string|max:255',
            'is_hidden' => 'required|boolean',
            'is_required' => 'required|boolean',
            'field_name' => 'required|string|max:255',
            'field_type' => 'required|string|max:255',
            'value_fields' => 'nullable|array',
            'display_on' => 'nullable|in:creation,reply,both',
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
            'business_unit_ids.required' => 'At least one business unit is required.',
            'business_unit_ids.array' => 'Business unit IDs must be an array.',
            'business_unit_ids.*.exists' => 'One or more selected business units do not exist.',
            'label_name.required' => 'Label name is required.',
            'label_name.string' => 'Label name must be a string.',
            'label_name.max' => 'Label name may not be greater than 255 characters.',
            'is_hidden.required' => 'is_hidden is required.',
            'is_hidden.boolean' => 'is_hidden must be true or false.',
            'is_required.required' => 'is_required is required.',
            'is_required.boolean' => 'is_required must be true or false.',
            'field_name.required' => 'Field type is required.',
            'field_name.string' => 'Field type must be a string.',
            'field_name.max' => 'Field type may not be greater than 255 characters.',
            'field_type.required' => 'Field type is required.',
            'field_type.string' => 'Field type must be a string.',
            'field_type.max' => 'Field type may not be greater than 255 characters.',
            'value_fields.array' => 'Value fields must be an array.',
        ];
    }
}
