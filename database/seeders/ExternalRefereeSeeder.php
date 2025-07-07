<?php

namespace Database\Seeders;

use App\Models\ExternalReferee;
use Illuminate\Database\Seeder;

class ExternalRefereeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $referees = [
            [
                'name' => 'Dr. John Smith',
                'email' => 'john.smith@medicenter.com',
                'phone' => '+1234567890',
                'organization' => 'Medical Center Hospital',
                'position' => 'Senior Physician',
                'specialty' => 'Cardiology',
                'address' => '123 Medical Center Ave, Suite 100'
            ],
            [
                'name' => 'Dr. Sarah Johnson',
                'email' => 'sarah.johnson@generalhospital.com',
                'phone' => '+1234567891',
                'organization' => 'General Hospital',
                'position' => 'Chief of Medicine',
                'specialty' => 'Internal Medicine',
                'address' => '456 Hospital Drive'
            ],
            [
                'name' => 'Dr. Michael Chen',
                'email' => 'michael.chen@citycare.com',
                'phone' => '+1234567892',
                'organization' => 'City Care Hospital',
                'position' => 'Department Head',
                'specialty' => 'Neurology',
                'address' => '789 Healthcare Blvd'
            ],
            [
                'name' => 'Dr. Emily Rodriguez',
                'email' => 'emily.rodriguez@pediatric.com',
                'phone' => '+1234567893',
                'organization' => 'Children\'s Medical Center',
                'position' => 'Senior Specialist',
                'specialty' => 'Pediatrics',
                'address' => '321 Children\'s Way'
            ],
            [
                'name' => 'Dr. David Wilson',
                'email' => 'david.wilson@heartclinic.com',
                'phone' => '+1234567894',
                'organization' => 'Heart & Vascular Clinic',
                'position' => 'Director',
                'specialty' => 'Cardiovascular Surgery',
                'address' => '555 Cardiac Court'
            ],
            [
                'name' => 'Dr. Lisa Thompson',
                'email' => 'lisa.thompson@womenshealth.com',
                'phone' => '+1234567895',
                'organization' => 'Women\'s Health Center',
                'position' => 'Lead Physician',
                'specialty' => 'Obstetrics & Gynecology',
                'address' => '777 Women\'s Health Plaza'
            ],
            [
                'name' => 'Dr. James Anderson',
                'email' => 'james.anderson@orthoclinic.com',
                'phone' => '+1234567896',
                'organization' => 'Orthopedic Specialty Clinic',
                'position' => 'Senior Surgeon',
                'specialty' => 'Orthopedics',
                'address' => '888 Bone & Joint Road'
            ],
            [
                'name' => 'Dr. Maria Garcia',
                'email' => 'maria.garcia@familycare.com',
                'phone' => '+1234567897',
                'organization' => 'Family Care Medical Group',
                'position' => 'Family Physician',
                'specialty' => 'Family Medicine',
                'address' => '444 Family Care Lane'
            ],
            [
                'name' => 'Dr. Robert Kim',
                'email' => 'robert.kim@oncology.com',
                'phone' => '+1234567898',
                'organization' => 'Cancer Treatment Center',
                'position' => 'Oncologist',
                'specialty' => 'Medical Oncology',
                'address' => '666 Cancer Care Way'
            ],
            [
                'name' => 'Dr. Jennifer Brown',
                'email' => 'jennifer.brown@dermatology.com',
                'phone' => '+1234567899',
                'organization' => 'Dermatology Associates',
                'position' => 'Dermatologist',
                'specialty' => 'Dermatology',
                'address' => '222 Skin Care Blvd'
            ]
        ];

        foreach ($referees as $referee) {
            ExternalReferee::create($referee);
        }
    }
}
