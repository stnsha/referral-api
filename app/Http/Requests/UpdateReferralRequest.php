<?php

namespace App\Http\Requests;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReferralRequest extends FormRequest
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
        $staticRules = [
            'referral.referral_id' => 'required|exists:referrals,id',
            'referral.business_unit_id_reply' => 'required|integer',
            'referral.updated_recipient_to' => 'nullable|integer',
            'referral.status' => 'required|integer',
            'referral.status_note' => 'nullable|string',
            'referral.additional_remarks' => 'nullable|string',
            'referral.referral_reason' => 'nullable|string',
            'referral.referral_condition' => 'nullable|string',
            'referral.medical_history' => 'nullable|string',

            'refer_another.refer_business_unit' => 'nullable|integer',
            'refer_another.refer_location' => 'nullable|integer',
            'refer_another.refer_to' => 'nullable|integer',
            'refer_another.additional_remarks_refer' => 'nullable|string',

            'attachments' => 'nullable|array'
        ];

        $fieldTypeRuleMap = [
            'text' => 'string',
            'textarea' => 'string',
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'datetime-local' => 'date',
            'time' => 'date_format:H:i',
            'url' => 'url',
            'tel' => 'string',
            'password' => 'string',
            'color' => 'string',
            'month' => 'date_format:Y-m',
            'week' => 'date_format:Y-\WW',
            'file' => 'file',
            'search' => 'string',
            'range' => 'numeric',
            'hidden' => 'string',
            'image' => 'image',
            'submit' => 'string',
            'reset' => 'string',
            'button' => 'string',
        ];

        $dynamicRules = [];

        // Skip form_data validation if status is 5 (patient not present)
        $status = $this->input('referral.status');

        if ($status != 5) {
            $buId = $this->input('referral.business_unit_id_reply');

            $forms = Form::where('business_unit_id', $buId)->with('form_details')->get();

            foreach ($forms as $form) {
                foreach ($form->form_details as $detail) {
                    $field = $detail->field_name;
                    $type = $detail->field_type;
                    $isRequired = $detail->is_required;

                    $rules = [];
                    $rules[] = $isRequired ? 'required' : 'nullable';

                    if (in_array($type, ['select', 'radio'])) {
                        $rules[] = 'integer';

                        $validIds = $form->form_details
                            ->where('field_name', $field)
                            ->pluck('id')
                            ->toArray();

                        $rules[] = 'in:' . implode(',', $validIds);
                    } elseif ($type === 'checkbox') {
                        $rules[] = 'array';

                        $validIds = $form->form_details
                            ->where('field_name', $field)
                            ->pluck('id')
                            ->toArray();

                        $dynamicRules["form_data.$buId.$field.*"] = 'integer|in:' . implode(',', $validIds);
                    } elseif (isset($fieldTypeRuleMap[$type])) {
                        $rules[] = $fieldTypeRuleMap[$type];
                    }

                    $dynamicRules["form_data.$buId.$field"] = implode('|', $rules);
                }
            }
        }

        return array_merge($staticRules, $dynamicRules);
    }

    public function messages(): array
    {
        $messages = [];

        // Skip form_data messages if status is 5 (patient not present)
        $status = $this->input('referral.status');

        if ($status != 5) {
            $buId = $this->input('referral.business_unit_id_reply');

            $forms = Form::where('business_unit_id', $buId)->with('form_details')->get();

            foreach ($forms as $form) {
                foreach ($form->form_details as $detail) {
                    $field = $detail->field_name;
                    $label = ucwords(str_replace('_', ' ', $field));
                    $type = $detail->field_type;
                    $path = "form_data.{$form->id}.$field";

                    if ($detail->is_required) {
                        $messages["$path.required"] = "$label is required.";
                    }

                    switch ($type) {
                        case 'email':
                            $messages["$path.email"] = "$label must be a valid email address.";
                            break;
                        case 'number':
                        case 'range':
                            $messages["$path.numeric"] = "$label must be a number.";
                            break;
                        case 'date':
                        case 'datetime-local':
                            $messages["$path.date"] = "$label must be a valid date.";
                            break;
                        case 'time':
                            $messages["$path.date_format"] = "$label must be in HH:MM format.";
                            break;
                        case 'month':
                            $messages["$path.date_format"] = "$label must be in YYYY-MM format.";
                            break;
                        case 'week':
                            $messages["$path.date_format"] = "$label must be in YYYY-WW format.";
                            break;
                        case 'file':
                            $messages["$path.file"] = "$label must be a valid file.";
                            break;
                        case 'image':
                            $messages["$path.image"] = "$label must be an image.";
                            break;
                        case 'url':
                            $messages["$path.url"] = "$label must be a valid URL.";
                            break;
                        case 'select':
                        case 'radio':
                            $messages["$path.integer"] = "$label must be a valid option.";
                            $messages["$path.in"] = "$label is not a valid selection.";
                            break;
                        case 'checkbox':
                            $messages["$path.array"] = "$label must be an array of selected options.";
                            $messages["$path.in"] = "$label contains an invalid selection.";
                            break;
                        default:
                            $messages["$path.string"] = "$label must be a string.";
                            break;
                    }
                }
            }
        }

        return $messages;
    }
}