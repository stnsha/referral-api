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
                ['label' => 'Have you experienced hearing loss recently?', 'field' => 'hearing_loss_recent', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Do you use any hearing aids?', 'field' => 'hearing_aid_usage', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Ear discomfort or pain?', 'field' => 'ear_discomfort', 'type' => 'checkbox', 'options' => ['Left Ear', 'Right Ear']],
            ],
            2 => [
                ['label' => "Baby's date of birth", 'field' => 'baby_dob', 'type' => 'date'],
                ['label' => 'Are you breastfeeding?', 'field' => 'breastfeeding_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Any recent vaccinations?', 'field' => 'recent_vaccinations', 'type' => 'checkbox', 'options' => ['BCG', 'Hep B', 'Polio']],
            ],
            3 => [
                ['label' => 'Do you have a history of chronic illness?', 'field' => 'chronic_illness_history', 'type' => 'text'],
                ['label' => 'Are you currently taking any medication?', 'field' => 'current_medications', 'type' => 'text'],
                ['label' => 'Have you undergone any surgery in the past year?', 'field' => 'recent_surgeries', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            4 => [
                ['label' => 'Do you wear glasses or contact lenses?', 'field' => 'vision_aid_usage', 'type' => 'radio', 'options' => ['Glasses', 'Contact Lenses', 'None']],
                ['label' => 'When was your last eye exam?', 'field' => 'last_eye_exam', 'type' => 'date'],
                ['label' => 'Do you experience blurry vision?', 'field' => 'blurry_vision', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            5 => [
                ['label' => 'Do you have any known drug allergies?', 'field' => 'drug_allergies', 'type' => 'text'],
                ['label' => 'Are you currently on any prescription medication?', 'field' => 'prescription_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Preferred pickup time', 'field' => 'pickup_time', 'type' => 'time'],
            ],
            6 => [
                ['label' => 'Which area of your body needs physiotherapy?', 'field' => 'targeted_area', 'type' => 'text'],
                ['label' => 'Pain level (1-10)', 'field' => 'pain_level', 'type' => 'number'],
                ['label' => 'Have you received physiotherapy before?', 'field' => 'previous_physiotherapy', 'type' => 'radio', 'options' => ['Yes', 'No']],
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
                            'is_required' => true,
                        ]);
                    }
                } else {
                    FormDetails::firstOrCreate([
                        'form_id' => $form->id,
                        'field_name' => $field['field'],
                        'field_type' => $field['type'],
                    ], [
                        'is_required' => true,
                        'field_value' => null,
                    ]);
                }
            }
        }
    }
}
