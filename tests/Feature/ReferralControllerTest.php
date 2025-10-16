<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use App\Models\Form;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralCreateForm;
use App\Models\ReferralDetails;
use App\Models\ReferralHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReferralControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); // Prevent actual email sending during tests
    }

    /**
     * Helper method to create a valid JWT payload
     */
    protected function mockJwtPayload(int $businessUnitId, int $staffId = 2222): array
    {
        return [
            'business_unit_id' => $businessUnitId,
            'staff_id' => $staffId,
        ];
    }

    /**
     * Helper method to make authenticated POST request with JWT payload
     */
    protected function postJsonWithJwt(string $uri, array $payload, int $businessUnitId, int $staffId = 2222)
    {
        // Add jwt_payload to the request  body
        $payloadWithJwt = array_merge($payload, [
            'jwt_payload' => $this->mockJwtPayload($businessUnitId, $staffId)
        ]);

        return $this->withoutMiddleware()->postJson($uri, $payloadWithJwt);
    }

    /**
     * Helper method to get business units from seeder data
     */
    protected function createBusinessUnits(): array
    {
        // Use actual data from BusinessUnitSeeder
        $businessUnit1 = BusinessUnit::firstOrCreate(
            ['name' => 'Alpro Physio'],
            ['staff_department_id' => 20, 'is_active' => 1]
        );

        $businessUnit2 = BusinessUnit::firstOrCreate(
            ['name' => 'Alpro Audiology'],
            ['staff_department_id' => 1, 'is_active' => 1]
        );

        return [$businessUnit1, $businessUnit2];
    }

    /**
     * Helper method to create test forms
     */
    protected function createFormWithDetails(int $businessUnitId): Form
    {
        $form = Form::factory()->create([
            'business_unit_id' => $businessUnitId,
            'label_name' => 'Test Form',
        ]);

        FormDetails::factory()->create([
            'form_id' => $form->id,
            'field_name' => 'targeted_area',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        FormDetails::factory()->create([
            'form_id' => $form->id,
            'field_name' => 'pain_level',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        return $form;
    }

    /**
     * Test Case 1: Internal referral (business unit to business unit)
     */
    public function test_store_internal_referral_success(): void
    {
        [$businessUnit1, $businessUnit2] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '101',
                    'referral_reason' => 'Balance issues',
                    'referral_condition' => 'Patient has dizziness',
                    'medical_history' => 'No prior issues',
                    'additional_remarks' => 'Urgent',
                    'nama_staff' => 'Test Staff',
                    'contact' => '0123456789',
                    'outlet' => 'TEST01',
                    'email' => 'test@test.com',
                    'designation' => 'Therapist',
                ],
                'recipient' => [
                    'staff_id' => 0,
                    'business_unit_id' => (string) $businessUnit2->id,
                    'location' => '350',
                ],
            ],
            'referral' => [
                'customer_id' => 10,
                'priority' => 2,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Lower back',
                    'pain_level' => '7',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201)
            ->assertJsonStructure(['id']);

        // Assertions
        $this->assertDatabaseHas('referrals', [
            'customer_id' => 10,
            'priority' => 2,
            'status' => 1, // Open status for internal referral
        ]);

        $referral = Referral::latest()->first();

        // Check hierarchies were created
        $this->assertEquals(2, $referral->referral_hierarchies->count());

        // Check first hierarchy (assignee)
        $firstHierarchy = $referral->referral_hierarchies->where('sequence', 1)->first();
        $this->assertEquals($businessUnit1->id, $firstHierarchy->business_unit_id);
        $this->assertEquals(2222, $firstHierarchy->staff_id);
        $this->assertTrue($firstHierarchy->is_filled);

        // Check second hierarchy (recipient)
        $secondHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();
        $this->assertEquals($businessUnit2->id, $secondHierarchy->business_unit_id);
        $this->assertFalse($secondHierarchy->is_filled);

        // Check create form was created
        $this->assertDatabaseHas('referral_create_forms', [
            'referral_hierarchy_id' => $firstHierarchy->id,
            'referral_reason' => 'Balance issues',
            'referral_condition' => 'Patient has dizziness',
            'medical_history' => 'No prior issues',
        ]);

        // Check referral details were created
        $this->assertEquals(2, ReferralDetails::where('referral_hierarchy_id', $firstHierarchy->id)->count());
    }

    /**
     * Test Case 2: External referral with new organization and new recipient
     */
    public function test_store_external_referral_with_new_organization_and_recipient(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'External specialist needed',
                    'referral_condition' => 'Ongoing lower back pain',
                    'medical_history' => 'No prior spinal injuries',
                    'additional_remarks' => 'Referral to external specialist',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'new_organization' => [
                        'name' => 'Hospital Tuanku Jaafar',
                        'address' => 'Jalan Rasah, Seremban',
                        'postcode' => '70300',
                        'state' => 'Negeri Sembilan',
                        'country' => 'Malaysia',
                    ],
                    'new_recipient' => [
                        'name' => 'Dr. Farisah',
                        'email' => 'dummy@dummy.com',
                        'phone' => '0123456789',
                        'position' => 'Medical Officer',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Teo Kwai Yen',
                    'ic' => '730711045286',
                    'gender' => 'Female',
                    'birth_date' => '1973-07-11',
                    'phone' => '0133502881',
                    'email' => '',
                    'address' => '5501-C LORONG 2 JALAN POKOK MANGGA',
                ],
                'priority' => 2,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Lower back',
                    'pain_level' => '7',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'pdf_base64']);

        // Verify external organization was created
        $this->assertDatabaseHas('external_organizations', [
            'name' => 'Hospital Tuanku Jaafar',
            'address' => 'Jalan Rasah, Seremban',
            'postcode' => '70300',
            'state' => 'Negeri Sembilan',
            'country' => 'Malaysia',
        ]);

        $organization = ExternalOrganization::where('name', 'Hospital Tuanku Jaafar')->first();

        // Verify external referee was created with organization link
        $this->assertDatabaseHas('external_referees', [
            'name' => 'Dr. Farisah',
            'email' => 'dummy@dummy.com',
            'phone' => '0123456789',
            'position' => 'Medical Officer',
            'external_organization_id' => $organization->id,
        ]);

        $referee = ExternalReferee::where('name', 'Dr. Farisah')->first();

        // Verify referral status is 4 (Closed) for external referral
        $referral = Referral::latest()->first();
        $this->assertEquals(4, $referral->status);
        $this->assertEquals(10, $referral->customer_id); // patient ID for external

        // Verify hierarchy has both external_referee_id and external_organization_id
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();
        $this->assertEquals($referee->id, $recipientHierarchy->external_referee_id);
        $this->assertEquals($organization->id, $recipientHierarchy->external_organization_id);

        // Verify PDF was generated and stored
        $this->assertNotNull($referral->encoded_base);
    }

    /**
     * Test Case 3: External referral with existing referee
     */
    public function test_store_external_referral_with_existing_referee(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        // Create existing organization and referee
        $organization = ExternalOrganization::factory()->create();
        $referee = ExternalReferee::factory()->create([
            'external_organization_id' => $organization->id,
        ]);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'External specialist needed',
                    'referral_condition' => 'Patient condition',
                    'medical_history' => 'No prior issues',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'referee' => $referee->id,
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Neck',
                    'pain_level' => '5',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        $this->assertEquals($referee->id, $recipientHierarchy->external_referee_id);
        $this->assertEquals(4, $referral->status); // Closed for external
    }

    /**
     * Test Case 4: External referral with new organization only (no specific referee)
     * Note: Using new_organization (not organization) to trigger external referral validation
     */
    public function test_store_external_referral_with_organization_only(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'Organization referral',
                    'referral_condition' => 'Patient needs specialist',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'new_organization' => [
                        'name' => 'Test Hospital',
                        'address' => 'Test Address',
                        'postcode' => '12345',
                        'state' => 'Test State',
                        'country' => 'Malaysia',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Female',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => '',
                    'address' => 'Test Address',
                ],
                'priority' => 2,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Back',
                    'pain_level' => '8',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        // Should have no referee but should have organization
        $this->assertNull($recipientHierarchy->external_referee_id);
        $this->assertNotNull($recipientHierarchy->external_organization_id);

        // Verify organization was created
        $this->assertDatabaseHas('external_organizations', [
            'name' => 'Test Hospital',
            'address' => 'Test Address',
        ]);
    }

    /**
     * Test Case 5: Referral with attachments
     */
    public function test_store_referral_with_attachments(): void
    {
        [$businessUnit1, $businessUnit2] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        // Create a simple 1x1 pixel PNG image in base64
        $base64Image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '101',
                    'referral_reason' => 'Test referral',
                    'referral_condition' => 'Test condition',
                    'medical_history' => 'None',
                    'nama_staff' => 'Test',
                    'contact' => '0123456789',
                    'outlet' => 'TEST',
                    'email' => 'test@test.com',
                    'designation' => 'Therapist',
                ],
                'recipient' => [
                    'staff_id' => 0,
                    'business_unit_id' => (string) $businessUnit2->id,
                    'location' => '350',
                ],
            ],
            'referral' => [
                'customer_id' => 10,
                'priority' => 1,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Arm',
                    'pain_level' => '3',
                ],
            ],
            'attachments' => [
                [
                    'name' => 'test-report.png',
                    'type' => 'image/png',
                    'size' => 14040,
                    'base64' => $base64Image,
                ],
            ],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $firstHierarchy = $referral->referral_hierarchies->where('sequence', 1)->first();

        $this->assertEquals(1, ReferralAttachment::where('referral_hierarchy_id', $firstHierarchy->id)->count());

        $attachment = ReferralAttachment::where('referral_hierarchy_id', $firstHierarchy->id)->first();
        $this->assertEquals('image/png', $attachment->file_type);
        $this->assertEquals(14040, $attachment->file_size);
    }

    /**
     * Test Case 6: Unauthorized business unit
     */
    public function test_store_referral_unauthorized_business_unit(): void
    {
        [$businessUnit1, $businessUnit2] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit2->id, // Different from JWT
                    'location' => '101',
                    'referral_reason' => 'Test',
                    'referral_condition' => 'Test condition',
                    'medical_history' => 'None',
                    'nama_staff' => 'Test',
                    'contact' => '0123456789',
                    'outlet' => 'TEST',
                    'email' => 'test@test.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'staff_id' => 0,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '350',
                ],
            ],
            'referral' => [
                'customer_id' => 10,
                'priority' => 2,
            ],
            'form_data' => [
                (string) $businessUnit2->id => [
                    'targeted_area' => 'Test',
                    'pain_level' => '5',
                ],
            ],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized: Cannot create referral for different business unit.',
            ]);
    }

    /**
     * Test Case 7: Missing business unit ID in JWT
     */
    public function test_store_referral_missing_business_unit_in_jwt(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '101',
                    'referral_reason' => 'Test',
                    'referral_condition' => 'Test',
                    'medical_history' => 'None',
                    'nama_staff' => 'Test',
                    'contact' => '0123456789',
                    'outlet' => 'TEST',
                    'email' => 'test@test.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'staff_id' => 0,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '350',
                ],
            ],
            'referral' => [
                'customer_id' => 10,
                'priority' => 2,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Test',
                    'pain_level' => '5',
                ],
            ],
        ];

        $response = $this->withoutMiddleware()
            ->withServerVariables(['jwt_payload' => []])
            ->postJson('/api/referral', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Business unit ID not found in session.',
            ]);
    }

    /**
     * Test Case 8: Validation failure
     */
    public function test_store_referral_validation_failure(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                ],
            ],
            // Missing required 'referral' field
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(422);
    }

    /**
     * Test Case 9: External referral sends email
     */
    public function test_store_external_referral_sends_email(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $organization = ExternalOrganization::factory()->create();
        $referee = ExternalReferee::factory()->create([
            'external_organization_id' => $organization->id,
            'email' => 'external@test.com',
        ]);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'Test',
                    'referral_condition' => 'Test condition',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'referee' => $referee->id,
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        // Verify email was queued/sent
        Mail::assertSent(\App\Mail\ExternalReferralNotification::class, function ($mail) use ($referee) {
            return $mail->hasTo($referee->email);
        });
    }

    /**
     * Test Case 10: New organization & new external referee & empty form data
     */
    public function test_store_external_new_org_new_referee_empty_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'External specialist needed',
                    'referral_condition' => 'Ongoing condition',
                    'medical_history' => 'No prior issues',
                    'additional_remarks' => 'Referral to external specialist',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'new_organization' => [
                        'name' => 'New Hospital',
                        'address' => 'Hospital Address',
                        'postcode' => '50000',
                        'state' => 'Kuala Lumpur',
                        'country' => 'Malaysia',
                    ],
                    'new_recipient' => [
                        'name' => 'Dr. New',
                        'email' => 'dr.new@hospital.com',
                        'phone' => '0123456789',
                        'position' => 'Specialist',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 2,
            ],
            'form_data' => [],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $this->assertEquals(4, $referral->status); // Closed for external

        // Verify both organization and referee were created
        $this->assertDatabaseHas('external_organizations', ['name' => 'New Hospital']);
        $this->assertDatabaseHas('external_referees', ['name' => 'Dr. New']);
    }

    /**
     * Test Case 11: New organization ONLY & empty form data
     */
    public function test_store_external_new_org_only_empty_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'Organization referral',
                    'referral_condition' => 'Patient needs specialist',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'new_organization' => [
                        'name' => 'Test Clinic',
                        'address' => 'Clinic Address',
                        'postcode' => '60000',
                        'state' => 'Selangor',
                        'country' => 'Malaysia',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Female',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => '',
                    'address' => 'Test Address',
                ],
                'priority' => 2,
            ],
            'form_data' => [],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        // Should have no referee but should have organization
        $this->assertNull($recipientHierarchy->external_referee_id);
        $this->assertNotNull($recipientHierarchy->external_organization_id);

        $this->assertDatabaseHas('external_organizations', ['name' => 'Test Clinic']);
    }

    /**
     * Test Case 12: Existing organization & new external referee & empty form data
     */
    public function test_store_external_existing_org_new_referee_empty_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $organization = ExternalOrganization::factory()->create();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'New doctor referral',
                    'referral_condition' => 'Patient condition',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'organization' => $organization->id,
                    'new_recipient' => [
                        'name' => 'Dr. Fresh',
                        'email' => 'dr.fresh@hospital.com',
                        'phone' => '0123456789',
                        'position' => 'New Specialist',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        // Verify new referee was created with existing organization
        $this->assertDatabaseHas('external_referees', [
            'name' => 'Dr. Fresh',
            'external_organization_id' => $organization->id,
        ]);

        $this->assertNotNull($recipientHierarchy->external_referee_id);
        $this->assertEquals($organization->id, $recipientHierarchy->external_organization_id);
    }

    /**
     * Test Case 13: Existing organization & new external referee & form data
     */
    public function test_store_external_existing_org_new_referee_with_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $organization = ExternalOrganization::factory()->create();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'New doctor referral',
                    'referral_condition' => 'Patient condition with details',
                    'medical_history' => 'Some history',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'organization' => $organization->id,
                    'new_recipient' => [
                        'name' => 'Dr. NewDoc',
                        'email' => 'dr.newdoc@hospital.com',
                        'phone' => '0123456789',
                        'position' => 'Specialist',
                    ],
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Female',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Shoulder',
                    'pain_level' => '6',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $firstHierarchy = $referral->referral_hierarchies->where('sequence', 1)->first();

        // Verify form data was saved
        $this->assertEquals(2, ReferralDetails::where('referral_hierarchy_id', $firstHierarchy->id)->count());

        // Verify new referee was created
        $this->assertDatabaseHas('external_referees', [
            'name' => 'Dr. NewDoc',
            'external_organization_id' => $organization->id,
        ]);
    }

    /**
     * Test Case 14: Existing organization & existing external referee & empty form data
     */
    public function test_store_external_existing_org_existing_referee_empty_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $organization = ExternalOrganization::factory()->create();
        $referee = ExternalReferee::factory()->create([
            'external_organization_id' => $organization->id,
        ]);

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'External specialist',
                    'referral_condition' => 'Patient condition',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'referee' => $referee->id,
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        $this->assertEquals($referee->id, $recipientHierarchy->external_referee_id);
        $this->assertEquals(4, $referral->status); // Closed for external
    }

    /**
     * Test Case 15: Existing organization ONLY & empty form data
     */
    public function test_store_external_existing_org_only_empty_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();

        $organization = ExternalOrganization::factory()->create();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'Organization referral',
                    'referral_condition' => 'Patient needs specialist at organization',
                    'medical_history' => 'None',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'organization' => $organization->id,
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Female',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => '',
                    'address' => 'Test Address',
                ],
                'priority' => 2,
            ],
            'form_data' => [],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();

        // Should have no referee but should have organization
        $this->assertNull($recipientHierarchy->external_referee_id);
        $this->assertEquals($organization->id, $recipientHierarchy->external_organization_id);
        $this->assertEquals(4, $referral->status); // Closed for external
    }

    /**
     * Test Case 16: Existing organization ONLY & form data
     */
    public function test_store_external_existing_org_only_with_form(): void
    {
        [$businessUnit1] = $this->createBusinessUnits();
        $this->createFormWithDetails($businessUnit1->id);

        $organization = ExternalOrganization::factory()->create();

        $payload = [
            'business_units' => [
                'assignee' => [
                    'staff_id' => 2222,
                    'business_unit_id' => (string) $businessUnit1->id,
                    'location' => '216',
                    'referral_reason' => 'Organization referral',
                    'referral_condition' => 'Patient needs specialist at organization',
                    'medical_history' => 'Some history',
                    'nama_staff' => 'USER',
                    'contact' => '601123232323',
                    'outlet' => 'JBPTF0',
                    'email' => 'tester@gmail.com',
                    'designation' => 'Tester',
                ],
                'recipient' => [
                    'organization' => $organization->id,
                ],
            ],
            'referral' => [
                'patient' => [
                    'id' => '10',
                    'name' => 'Test Patient',
                    'ic' => '123456789',
                    'gender' => 'Male',
                    'birth_date' => '1990-01-01',
                    'phone' => '0123456789',
                    'email' => 'patient@test.com',
                    'address' => 'Test Address',
                ],
                'priority' => 1,
            ],
            'form_data' => [
                (string) $businessUnit1->id => [
                    'targeted_area' => 'Knee',
                    'pain_level' => '9',
                ],
            ],
            'attachments' => [],
        ];

        $response = $this->postJsonWithJwt('/api/referral', $payload, $businessUnit1->id);

        $response->assertStatus(201);

        $referral = Referral::latest()->first();
        $recipientHierarchy = $referral->referral_hierarchies->where('sequence', 2)->first();
        $firstHierarchy = $referral->referral_hierarchies->where('sequence', 1)->first();

        // Verify form data was saved
        $this->assertEquals(2, ReferralDetails::where('referral_hierarchy_id', $firstHierarchy->id)->count());

        // Should have no referee but should have organization
        $this->assertNull($recipientHierarchy->external_referee_id);
        $this->assertEquals($organization->id, $recipientHierarchy->external_organization_id);
        $this->assertEquals(4, $referral->status); // Closed for external
    }
}
