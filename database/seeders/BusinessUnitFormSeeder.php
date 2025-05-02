<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Form;
use App\Models\FormDetails;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BusinessUnitFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $fieldTypes = [
        //     'button',
        //     'checkbox',
        //     'color',
        //     'date',
        //     'datetime-local',
        //     'email',
        //     'file',
        //     'hidden',
        //     'image',
        //     'month',
        //     'number',
        //     'password',
        //     'radio',
        //     'range',
        //     'reset',
        //     'search',
        //     'submit',
        //     'tel',
        //     'text',
        //     'time',
        //     'url',
        //     'week',
        // ];

        // foreach (range(1, 10) as $i) {
        //     $fieldType = Arr::random($fieldTypes);
        //     $label = fake()->words(2, true);
        //     $fieldName = Str::slug($label, '_');

        //     $form = Form::create([
        //         'business_unit_id' => rand(1, 6),
        //         'label_name' => $label,
        //         'is_hidden' => fake()->boolean(),
        //     ]);

        //     if (in_array($fieldType, ['checkbox', 'radio', 'select'])) {
        //         $options = ['Option A', 'Option B', 'Option C'];
        //         foreach ($options as $option) {
        //             FormDetails::create([
        //                 'form_id' => $form->id,
        //                 'is_required' => fake()->boolean(),
        //                 'field_name' => $fieldName,
        //                 'field_type' => $fieldType,
        //                 'field_value' => $option,
        //             ]);
        //         }
        //     } else {
        //         FormDetails::create([
        //             'form_id' => $form->id,
        //             'is_required' => fake()->boolean(),
        //             'field_name' => $fieldName,
        //             'field_type' => $fieldType,
        //             'field_value' => null,
        //         ]);
        //     }
        // }

        $customQuestions = [
            1 => [
                ['label' => 'Have you experienced hearing loss recently?', 'field' => 'hearing_loss_recent', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Do you use any hearing aids?', 'field' => 'hearing_aid_usage', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Ear discomfort or pain?', 'field' => 'ear_discomfort', 'type' => 'checkbox', 'options' => ['Left Ear', 'Right Ear']],
            ],
            21 => [
                ['label' => "Baby's date of birth", 'field' => 'baby_dob', 'type' => 'date'],
                ['label' => 'Are you breastfeeding?', 'field' => 'breastfeeding_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Any recent vaccinations?', 'field' => 'recent_vaccinations', 'type' => 'checkbox', 'options' => ['BCG', 'Hep B', 'Polio']],
            ],
            2 => [
                ['label' => 'Do you have a history of chronic illness?', 'field' => 'chronic_illness_history', 'type' => 'text'],
                ['label' => 'Are you currently taking any medication?', 'field' => 'current_medications', 'type' => 'text'],
                ['label' => 'Have you undergone any surgery in the past year?', 'field' => 'recent_surgeries', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            35 => [
                ['label' => 'Do you wear glasses or contact lenses?', 'field' => 'vision_aid_usage', 'type' => 'radio', 'options' => ['Glasses', 'Contact Lenses', 'None']],
                ['label' => 'When was your last eye exam?', 'field' => 'last_eye_exam', 'type' => 'date'],
                ['label' => 'Do you experience blurry vision?', 'field' => 'blurry_vision', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
            1 => [
                ['label' => 'Do you have any known drug allergies?', 'field' => 'drug_allergies', 'type' => 'text'],
                ['label' => 'Are you currently on any prescription medication?', 'field' => 'prescription_status', 'type' => 'radio', 'options' => ['Yes', 'No']],
                ['label' => 'Preferred pickup time', 'field' => 'pickup_time', 'type' => 'time'],
            ],
            20 => [
                ['label' => 'Which area of your body needs physiotherapy?', 'field' => 'targeted_area', 'type' => 'text'],
                ['label' => 'Pain level (1-10)', 'field' => 'pain_level', 'type' => 'number'],
                ['label' => 'Have you received physiotherapy before?', 'field' => 'previous_physiotherapy', 'type' => 'radio', 'options' => ['Yes', 'No']],
            ],
        ];

        foreach ($customQuestions as $staff_department_id => $fields) {
            $businessUnitId = BusinessUnit::where('staff_department_id', $staff_department_id)->value('id');

            foreach ($fields as $field) {
                $form = Form::create([
                    'business_unit_id' => $businessUnitId,
                    'label_name' => $field['label'],
                    'is_hidden' => false,
                ]);

                if (in_array($field['type'], ['checkbox', 'radio', 'select']) && isset($field['options'])) {
                    foreach ($field['options'] as $option) {
                        FormDetails::create([
                            'form_id' => $form->id,
                            'is_required' => true,
                            'field_name' => $field['field'],
                            'field_type' => $field['type'],
                            'field_value' => $option,
                        ]);
                    }
                } else {
                    FormDetails::create([
                        'form_id' => $form->id,
                        'is_required' => true,
                        'field_name' => $field['field'],
                        'field_type' => $field['type'],
                        'field_value' => null,
                    ]);
                }
            }
        }
    }
}
