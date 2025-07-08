<?php

namespace Database\Seeders;

use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use Illuminate\Database\Seeder;

class ExternalRefereeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = ExternalOrganization::create([
            'name' => 'Medical Center Hospital',
            'address' => '123 Medical Center Ave, Suite 100',
            'postcode' => '12345',
            'state' => 'California',
            'country' => 'United States',
        ]);

        $referees = [
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. John Smith',
                'email' => 'john.smith@medicenter.com',
                'phone' => '+1234567890',
                'position' => 'Senior Physician',
                'specialty' => 'Cardiology',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Sarah Johnson',
                'email' => 'sarah.johnson@generalhospital.com',
                'phone' => '+1234567891',
                'position' => 'Chief of Medicine',
                'specialty' => 'Internal Medicine',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Michael Chen',
                'email' => 'michael.chen@citycare.com',
                'phone' => '+1234567892',
                'position' => 'Department Head',
                'specialty' => 'Neurology',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Emily Rodriguez',
                'email' => 'emily.rodriguez@pediatric.com',
                'phone' => '+1234567893',
                'position' => 'Senior Specialist',
                'specialty' => 'Pediatrics',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. David Wilson',
                'email' => 'david.wilson@heartclinic.com',
                'phone' => '+1234567894',
                'position' => 'Director',
                'specialty' => 'Cardiovascular Surgery',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Lisa Thompson',
                'email' => 'lisa.thompson@womenshealth.com',
                'phone' => '+1234567895',
                'position' => 'Lead Physician',
                'specialty' => 'Obstetrics & Gynecology',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. James Anderson',
                'email' => 'james.anderson@orthoclinic.com',
                'phone' => '+1234567896',
                'position' => 'Senior Surgeon',
                'specialty' => 'Orthopedics',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Maria Garcia',
                'email' => 'maria.garcia@familycare.com',
                'phone' => '+1234567897',
                'position' => 'Family Physician',
                'specialty' => 'Family Medicine',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Robert Kim',
                'email' => 'robert.kim@oncology.com',
                'phone' => '+1234567898',
                'position' => 'Oncologist',
                'specialty' => 'Medical Oncology',
            ],
            [
                'external_organization_id' => $organization->id,
                'name' => 'Dr. Jennifer Brown',
                'email' => 'jennifer.brown@dermatology.com',
                'phone' => '+1234567899',
                'position' => 'Dermatologist',
                'specialty' => 'Dermatology',
            ]
        ];

        foreach ($referees as $referee) {
            ExternalReferee::create($referee);
        }
    }
}
