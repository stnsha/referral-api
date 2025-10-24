<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\FormDetails;
use Illuminate\Database\Seeder;

class BusinessUnitFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customQuestions = [
            1 => [
                ['label' => 'Recent hearing loss', 'field' => 'hearing_loss_recent', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Hearing aid usage', 'field' => 'hearing_aid_usage', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Ear discomfort', 'field' => 'ear_discomfort', 'type' => 'checkbox', 'options' => ['Left Ear', 'Right Ear']],
            ],
            2 => [
                ['label' => "Baby's date of birth", 'field' => 'baby_dob', 'type' => 'date'],
                ['label' => 'Breastfeeding status', 'field' => 'breastfeeding_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Recent vaccinations', 'field' => 'recent_vaccinations', 'type' => 'checkbox', 'options' => ['BCG', 'Hep B', 'Polio']],
            ],
            3 => [
                ['label' => 'Chronic illness history', 'field' => 'chronic_illness_history', 'type' => 'text'],
                ['label' => 'Current medications', 'field' => 'current_medications', 'type' => 'text'],
                ['label' => 'Recent surgeries (past year)', 'field' => 'recent_surgeries', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            4 => [
                ['label' => 'Vision aid usage', 'field' => 'vision_aid_usage', 'type' => 'radio', 'options' => ['Glasses', 'Contact Lenses', 'None']],
                ['label' => 'Last eye exam date', 'field' => 'last_eye_exam', 'type' => 'date'],
                ['label' => 'Blurry vision', 'field' => 'blurry_vision', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            5 => [
                ['label' => 'Known drug allergies', 'field' => 'drug_allergies', 'type' => 'text'],
                ['label' => 'Prescription medication status', 'field' => 'prescription_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Preferred pickup time', 'field' => 'pickup_time', 'type' => 'time'],
            ],
            6 => [
                ['label' => 'Area needing physiotherapy', 'field' => 'targeted_area', 'type' => 'text'],
                ['label' => 'Pain level (1-10)', 'field' => 'pain_level', 'type' => 'number'],
                ['label' => 'Previous physiotherapy experience', 'field' => 'previous_physiotherapy', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
        ];

        foreach ($customQuestions as $business_unit_id => $fields) {
            foreach ($fields as $field) {
                $form = Form::firstOrCreate([
                    'business_unit_id' => $business_unit_id,
                    'label_name' => $field['label'],
                ], [
                    'is_hidden' => false,
                ]);

                if (in_array($field['type'], ['checkbox', 'radio', 'select']) && isset($field['options'])) {
                    foreach ($field['options'] as $option) {
                        FormDetails::firstOrCreate([
                            'form_id' => $form->id,
                            'field_name' => $field['field'],
                            'field_type' => $field['type'],
                            'field_value' => $option,
                        ], [
                            'is_required' => false,
                        ]);
                    }
                } else {
                    FormDetails::firstOrCreate([
                        'form_id' => $form->id,
                        'field_name' => $field['field'],
                        'field_type' => $field['type'],
                    ], [
                        'is_required' => false,
                        'field_value' => null,
                    ]);
                }
            }
        }
    }
}