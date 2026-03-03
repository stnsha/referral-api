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
            'referral.location_to' => 'nullable|integer',
            'referral.status' => 'required|integer',
            'referral.status_note' => 'nullable|string',
            'referral.additional_remarks' => 'nullable|string',
            'referral.referral_reason' => 'nullable|string',
            'referral.referral_condition' => 'nullable|string',
            'referral.medical_history' => 'nullable|string',
            'referral.post_diagnosis' => 'nullable|string',
            'referral.outcome' => 'nullable|string',
            'referral.feedback' => 'nullable|string',

            'refer_another.is_external_referral' => 'nullable|boolean',
            'refer_another.refer_business_unit' => 'nullable|integer',
            'refer_another.refer_location' => 'nullable|integer',
            'refer_another.refer_to' => 'nullable|integer',
            'refer_another.refer_organization' => 'nullable|integer',
            'refer_another.refer_referee' => 'nullable|integer',
            'refer_another.referral_reason' => 'nullable|string',
            'refer_another.referral_condition' => 'nullable|string',
            'refer_another.medical_history' => 'nullable|string',
            'refer_another.priority' => 'nullable|integer|in:1,2,3',
            'refer_another.additional_remarks_refer' => 'nullable|string',
            'refer_another.new_organization.name' => 'nullable|string',
            'refer_another.new_organization.address' => 'nullable|string',
            'refer_another.new_organization.postcode' => 'nullable|string',
            'refer_another.new_organization.state' => 'nullable|string',
            'refer_another.new_organization.country' => 'nullable|string',
            'refer_another.new_recipient.name' => 'nullable|string',
            'refer_another.new_recipient.email' => 'nullable|email',
            'refer_another.new_recipient.phone' => 'nullable|string',
            'refer_another.new_recipient.position' => 'nullable|string',

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

            $forms = Form::whereHas('business_units', function ($q) use ($buId) {
                $q->where('business_units.id', $buId);
            })->where('display_on', '!=', 'creation')
              ->with(['form_details', 'conditions'])
              ->get();

            foreach ($forms as $form) {
                $hasConditions = $form->conditions->isNotEmpty();

                foreach ($form->form_details as $detail) {
                    $field = $detail->field_name;
                    $type = $detail->field_type;
                    // Conditioned forms may not be shown depending on creation answers;
                    // their required fields are enforced client-side only.
                    $isRequired = !$hasConditions && $detail->is_required;

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

            $forms = Form::whereHas('business_units', function ($q) use ($buId) {
                $q->where('business_units.id', $buId);
            })->where('display_on', '!=', 'creation')
              ->with('form_details')
              ->get();

            foreach ($forms as $form) {
                foreach ($form->form_details as $detail) {
                    $field = $detail->field_name;
                    $label = ucwords(str_replace('_', ' ', $field));
                    $type = $detail->field_type;
                    $path = "form_data.$buId.$field";

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