<?php

namespace App\Http\Requests;

use App\Models\Form;
use App\Models\FormDetails;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
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
        // Check if this is an external referral
        // External if has: referee, new_organization, new_recipient, or organization (existing external org)
        // $isExternalReferral = $this->input('business_units.recipient.new_organization') !== null
        //     || $this->input('business_units.recipient.new_recipient') !== null
        //     || $this->input('business_units.recipient.organization') !== null;

        $staticRules = [
            'business_units.assignee.staff_id' => 'required|integer',
            'business_units.assignee.business_unit_id' => 'required|integer',
            'business_units.assignee.location' => 'required|integer',
            'business_units.assignee.referral_reason' => 'required|string',
            'business_units.assignee.referral_condition' => 'required|string',
            'business_units.assignee.medical_history' => 'nullable|string',
            'business_units.assignee.additional_remarks' => 'nullable|string',

            'business_units.recipient.staff_id' => 'nullable|integer',
            'business_units.recipient.business_unit_id' => 'nullable|integer',
            'business_units.recipient.location' => 'nullable|integer',

            'business_units.recipient.organization' => 'nullable|integer',
            'business_units.recipient.location_organization' => 'nullable|string',
            'business_units.recipient.referee' => 'nullable|integer',

            // New organization fields for external referral
            'business_units.recipient.new_organization.name' => 'nullable|string',
            'business_units.recipient.new_organization.address' => 'nullable|string',
            'business_units.recipient.new_organization.postcode' => 'nullable|string',
            'business_units.recipient.new_organization.state' => 'nullable|string',
            'business_units.recipient.new_organization.country' => 'nullable|string',

            // New recipient fields for external referral (optional)
            'business_units.recipient.new_recipient.name' => 'nullable|string',
            'business_units.recipient.new_recipient.email' => 'nullable|email',
            'business_units.recipient.new_recipient.phone' => 'nullable|string',
            'business_units.recipient.new_recipient.position' => 'nullable|string',
            'business_units.recipient.new_recipient.specialty' => 'nullable|string',

            // customer_id required only for internal referrals
            'referral.customer_id' => 'required|integer',
            'referral.priority' => 'required|integer',

            'attachments' => 'nullable|array',

            // form_data is now optional and can be empty array
            'form_data' => 'nullable|array'
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

        $buId = $this->input('business_units.assignee.business_unit_id');
        $formData = $this->input("form_data.$buId");

        // Only apply dynamic rules if form_data for this business unit is not empty
        if (!empty($formData) && is_array($formData)) {
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

                        $rules[] = 'in:' . implode(',', $validIds);
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

        $buId = $this->input('business_units.assignee.business_unit_id');
        $formData = $this->input("form_data.$buId");

        // Only generate messages if form_data for this business unit is not empty
        if (!empty($formData) && is_array($formData)) {
            $forms = Form::where('business_unit_id', $buId)->with('form_details')->get();

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