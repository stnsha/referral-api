<?php

namespace Database\Seeders;

use App\Models\Referral;
use App\Models\ReferralHistory;
use App\Models\ReferralDetails;
use App\Models\Form;
use App\Models\FormDetails;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReferralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Customer data with business units and outlets
        $staffs = [
            ["staff_id" => "3580", "business_unit" => "5", "location" => "1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,48,49,50,51,53,54,55,56,57,58,59,61,62,63,64,65,66,67,68,69,70,71,72,77,78,79,81,82,83,85,87,88,93,104,105,106,107,108,165,109,166,112,113,114,115,116,117,118,119,120,121,123,124,125,126,127,130,133,134,135,136,137,141,142,164,143,144,146,147,148,149,150,151,152,153,154,155,157,158,159,160,162,163,169,170,171,173,177,182,186,189,190,191,192,193,204,196,197,198,199,200,202,203,205,207,208,210,221,211,212,213,218,223,224,225,226,227,228,229,233,234,235,236,238,239,240,242,243,244,245,246,247,251,252,253,254,255,256,257,258,259,261,262,263,264,265,266,267,268,269,270,273,274,275,276,277,278,279,281,282,283,284,285,286,287,288,290,291,292,293,294,297,298,299,300,305,306,307,308,309,311,312,313,314,315,319,320,321,322,323,324,325,327,329,331,333,335,339,344,345,346,347,348,349,351,352,355,358,359,360,361,363,364,372,373,374,375,384"],
            ["staff_id" => "3581", "business_unit" => "3", "location" => "103,172,280,295,296,302,303,304,316,328,343,357,365,366,376,377"],
            ["staff_id" => "2", "business_unit" => "2", "location" => "237,336,342,383"],
            ["staff_id" => "2222", "business_unit" => "6", "location" => "99,100,101,102,161,216,217,232,260,317,318,337,338,354,356,362,378"],
            ["staff_id" => "3333", "business_unit" => "4", "location" => "380,381"],
            ["staff_id" => "3582", "business_unit" => "1", "location" => "350"]
        ];

        // Helper function to get staff and location by business unit
        $getStaffByBusinessUnit = function ($businessUnitId) use ($staffs) {
            foreach ($staffs as $staff) {
                if ($staff['business_unit'] == $businessUnitId) {
                    $locations = explode(',', $staff['location']);
                    return [
                        'staff_id' => $staff['staff_id'],
                        'location' => $locations[0] // Use first outlet as location
                    ];
                }
            }
            return ['staff_id' => null, 'location' => null];
        };

        // Create 5 dummy referrals
        $referrals = [
            [
                'customer_id' => 10,
                'priority' => 1, // High
                'status' => 2, // In Progress
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 3, // Alpro Clinic
                        'staff_id' => $getStaffByBusinessUnit(3)['staff_id'],
                        'location' => $getStaffByBusinessUnit(3)['location'],
                        'referral_reason' => 'Patient experiencing chronic back pain',
                        'referral_condition' => 'Lower back pain persisting for 3 months, affecting daily activities',
                        'medical_history' => 'Previous herniated disc surgery in 2020',
                        'additional_remarks' => 'Patient prefers morning appointments',
                        'is_filled' => true
                    ],
                    [
                        'sequence' => 2,
                        'business_unit_id' => 6, // Alpro Physio
                        'staff_id' => null,
                        'location' => $getStaffByBusinessUnit(6)['location'],
                        'referral_reason' => '',
                        'referral_condition' => '',
                        'medical_history' => '',
                        'additional_remarks' => '',
                        'is_filled' => false
                    ]
                ]
            ],
            [
                'customer_id' => 10,
                'priority' => 2, // Medium
                'status' => 1, // Open
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 1, // Alpro Audiology
                        'staff_id' => $getStaffByBusinessUnit(1)['staff_id'],
                        'location' => $getStaffByBusinessUnit(1)['location'],
                        'referral_reason' => 'Hearing loss assessment required',
                        'referral_condition' => 'Patient reports difficulty hearing in noisy environments',
                        'medical_history' => 'Family history of hearing loss',
                        'additional_remarks' => 'Patient works in construction industry',
                        'is_filled' => true
                    ]
                ]
            ],
            [
                'customer_id' => 10,
                'priority' => 3, // Low
                'status' => 3, // Referred
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 2, // Alpro Baby
                        'staff_id' => $getStaffByBusinessUnit(2)['staff_id'],
                        'location' => $getStaffByBusinessUnit(2)['location'],
                        'referral_reason' => 'Newborn health checkup',
                        'referral_condition' => 'Routine newborn examination and vaccination schedule',
                        'medical_history' => 'Normal delivery, no complications',
                        'additional_remarks' => 'First-time parents, need guidance',
                        'is_filled' => true
                    ],
                    [
                        'sequence' => 2,
                        'business_unit_id' => 3, // Alpro Clinic
                        'staff_id' => null,
                        'location' => $getStaffByBusinessUnit(3)['location'],
                        'referral_reason' => '',
                        'referral_condition' => '',
                        'medical_history' => '',
                        'additional_remarks' => '',
                        'is_filled' => false
                    ]
                ]
            ],
            [
                'customer_id' => 10,
                'priority' => 1, // High
                'status' => 4, // Closed
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 4, // Alpro Optisaver
                        'staff_id' => $getStaffByBusinessUnit(4)['staff_id'],
                        'location' => $getStaffByBusinessUnit(4)['location'],
                        'referral_reason' => 'Vision deterioration',
                        'referral_condition' => 'Sudden blurry vision in left eye',
                        'medical_history' => 'Diabetes Type 2, hypertension',
                        'additional_remarks' => 'Urgent consultation required',
                        'is_filled' => true
                    ]
                ]
            ],
            [
                'customer_id' => 10,
                'priority' => 2, // Medium
                'status' => 2, // In Progress
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 5, // Alpro Pharmacy
                        'staff_id' => $getStaffByBusinessUnit(5)['staff_id'],
                        'location' => $getStaffByBusinessUnit(5)['location'],
                        'referral_reason' => 'Medication consultation',
                        'referral_condition' => 'Patient needs medication review for multiple prescriptions',
                        'medical_history' => 'Multiple chronic conditions requiring medication management',
                        'additional_remarks' => 'Patient has drug allergies to penicillin',
                        'is_filled' => true
                    ],
                    [
                        'sequence' => 2,
                        'business_unit_id' => 3, // Alpro Clinic
                        'staff_id' => null,
                        'location' => $getStaffByBusinessUnit(3)['location'],
                        'referral_reason' => '',
                        'referral_condition' => '',
                        'medical_history' => '',
                        'additional_remarks' => '',
                        'is_filled' => false
                    ]
                ]
            ]
        ];

        foreach ($referrals as $referralData) {
            // Create referral
            $referral = Referral::firstOrCreate([
                'customer_id' => $referralData['customer_id'],
                'priority' => $referralData['priority'],
                'status' => $referralData['status'],
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 5))
            ]);

            // Create referral histories
            foreach ($referralData['histories'] as $historyData) {
                $referralHistory = ReferralHistory::firstOrCreate([
                    'referral_id' => $referral->id,
                    'staff_id' => $historyData['staff_id'],
                    'business_unit_id' => $historyData['business_unit_id'],
                    'location' => $historyData['location'],
                    'sequence' => $historyData['sequence'],
                    'referral_reason' => $historyData['referral_reason'],
                    'referral_condition' => $historyData['referral_condition'],
                    'medical_history' => $historyData['medical_history'],
                    'additional_remarks' => $historyData['additional_remarks'],
                    'is_filled' => $historyData['is_filled'],
                    'created_at' => Carbon::now()->subDays(rand(1, 25)),
                    'updated_at' => Carbon::now()->subDays(rand(0, 3))
                ]);

                // Create referral details only for filled histories
                if ($historyData['is_filled']) {
                    $this->createReferralDetails($referralHistory, $historyData['business_unit_id']);
                }
            }
        }

        // Create 2 dummy external referrals
        $externalReferrals = [
            [
                'customer_id' => 10,
                'priority' => 1, // High
                'status' => 1, // Referred
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 3, // Alpro Clinic
                        'staff_id' => $getStaffByBusinessUnit(3)['staff_id'],
                        'location' => $getStaffByBusinessUnit(3)['location'],
                        'referral_reason' => 'Complex cardiac condition requiring specialist care',
                        'referral_condition' => 'Patient presents with irregular heartbeat and chest pain',
                        'medical_history' => 'Previous heart attack, family history of cardiac disease',
                        'additional_remarks' => 'Urgent referral to cardiology specialist',
                        'is_filled' => true
                    ],
                    [
                        'sequence' => 2,
                        'business_unit_id' => null,
                        'staff_id' => null,
                        'location' => null,
                        'referral_reason' => '',
                        'referral_condition' => '',
                        'medical_history' => '',
                        'additional_remarks' => '',
                        'is_filled' => false,
                        'external_referee_id' => 1 // Dr. John Smith - Cardiology
                    ]
                ]
            ],
            [
                'customer_id' => 10,
                'priority' => 2, // Medium
                'status' => 1, // In Progress
                'histories' => [
                    [
                        'sequence' => 1,
                        'business_unit_id' => 1, // Alpro Audiology
                        'staff_id' => $getStaffByBusinessUnit(1)['staff_id'],
                        'location' => $getStaffByBusinessUnit(1)['location'],
                        'referral_reason' => 'Neurological hearing assessment required',
                        'referral_condition' => 'Patient experiencing sudden hearing loss with neurological symptoms',
                        'medical_history' => 'Recent head trauma, tinnitus',
                        'additional_remarks' => 'Requires specialized neurological evaluation',
                        'is_filled' => true
                    ],
                    [
                        'sequence' => 2,
                        'business_unit_id' => null,
                        'staff_id' => null,
                        'location' => null,
                        'referral_reason' => '',
                        'referral_condition' => '',
                        'medical_history' => '',
                        'additional_remarks' => '',
                        'is_filled' => false,
                        'external_referee_id' => 3 // Dr. Michael Chen - Neurology
                    ]
                ]
            ]
        ];

        foreach ($externalReferrals as $referralData) {
            // Create external referral
            $referral = Referral::firstOrCreate([
                'customer_id' => $referralData['customer_id'],
                'priority' => $referralData['priority'],
                'status' => $referralData['status'],
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 5))
            ]);

            // Create referral histories for external referrals
            foreach ($referralData['histories'] as $historyData) {
                $referralHistory = ReferralHistory::firstOrCreate([
                    'referral_id' => $referral->id,
                    'staff_id' => $historyData['staff_id'],
                    'business_unit_id' => $historyData['business_unit_id'],
                    'location' => $historyData['location'],
                    'sequence' => $historyData['sequence'],
                    'referral_reason' => $historyData['referral_reason'],
                    'referral_condition' => $historyData['referral_condition'],
                    'medical_history' => $historyData['medical_history'],
                    'additional_remarks' => $historyData['additional_remarks'],
                    'is_filled' => $historyData['is_filled'],
                    'external_referee_id' => $historyData['external_referee_id'] ?? null,
                    'created_at' => Carbon::now()->subDays(rand(1, 25)),
                    'updated_at' => Carbon::now()->subDays(rand(0, 3))
                ]);

                // Create referral details only for filled histories
                if ($historyData['is_filled']) {
                    $this->createReferralDetails($referralHistory, $historyData['business_unit_id']);
                }
            }
        }
    }

    /**
     * Create referral details based on business unit forms
     */
    private function createReferralDetails($referralHistory, $businessUnitId)
    {
        $forms = Form::where('business_unit_id', $businessUnitId)->get();

        foreach ($forms as $form) {
            $formDetail = FormDetails::where('form_id', $form->id)->first();

            if ($formDetail) {
                $value = $this->generateSampleValue($formDetail->field_type, $formDetail->field_name, $formDetail->field_value, $formDetail->id);

                ReferralDetails::create([
                    'referral_history_id' => $referralHistory->id,
                    'form_id' => $form->id,
                    'value' => $value
                ]);
            }
        }
    }

    /**
     * Generate sample values based on field type
     */
    private function generateSampleValue($fieldType, $fieldName, $fieldValue = null, $formDetailId = null)
    {
        switch ($fieldType) {
            case 'radio':
            case 'checkbox':
                return $formDetailId ?? ($fieldValue ?? 'Yes');
            case 'select':
            case 'date':
                return Carbon::now()->subDays(rand(1, 365))->format('Y-m-d');
            case 'time':
                return '09:00';
            case 'number':
                if (str_contains($fieldName, 'pain')) {
                    return rand(1, 10);
                }
                return rand(1, 100);
            case 'text':
            default:
                return $this->generateSampleText($fieldName);
        }
    }

    /**
     * Generate sample text based on field name
     */
    private function generateSampleText($fieldName)
    {
        $sampleTexts = [
            'targeted_area' => 'Lower back and spine',
            'chronic_illness_history' => 'Diabetes, Hypertension',
            'current_medications' => 'Metformin, Lisinopril',
            'drug_allergies' => 'Penicillin, Sulfa drugs',
            'hearing_loss_recent' => 'Yes',
            'hearing_aid_usage' => 'No',
            'breastfeeding_status' => 'Yes',
            'vision_aid_usage' => 'Glasses',
            'prescription_status' => 'Yes',
            'previous_physiotherapy' => 'Yes'
        ];

        return $sampleTexts[$fieldName] ?? 'Sample data for ' . $fieldName;
    }
}
