<?php

namespace Tests\Feature;

use App\Models\ConsumptionRecord;
use App\Models\Donor;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_api_login_route_is_registered(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_mobile_signup_creates_an_account_and_returns_a_login_token(): void
    {
        $response = $this->postJson('/api/signup', [
            'full_name' => 'Nerissa Kane',
            'username' => 'nerissa_kane',
            'role' => 'Medical Technologist',
            'contact' => '09181234567',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'username', 'name', 'role']])
            ->assertJsonPath('user.username', 'nerissa_kane');

        $this->assertDatabaseHas('users', [
            'username' => 'nerissa_kane',
            'role' => 'Medical Technologist',
            'station' => '',
        ]);
    }

    public function test_mobile_signup_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/signup', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'username', 'role', 'contact', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_staff_can_login_and_search_donors_via_api(): void
    {
        $user = User::factory()->create([
            'username' => 'mobiletech',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $login = $this->postJson('/api/login', [
            'username' => 'mobiletech',
            'password' => 'secret123',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'username', 'name', 'role']]);

        $token = $login->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Mobile Test Donor',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Male',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000001',
            'registered_date' => '2026-01-10',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-01-10',
            'blood_type' => 'O+',
            'location' => 'CRH',
            'bags' => 1,
        ]);

        $this->withToken($token)
            ->getJson('/api/donors/search?q=Mobile')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonFragment(['name' => 'Mobile Test Donor']);

        $this->withToken($token)
            ->getJson('/api/donors/returning?q=m')
            ->assertOk()
            ->assertJsonPath('donors.0.name', 'Mobile Test Donor');
    }

    public function test_mobile_first_time_donor_registration_stores_complete_passed_screening_details(): void
    {
        User::factory()->create([
            'username' => 'mobile-first-donor-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-first-donor-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/donors/meta')
            ->assertOk()
            ->assertJsonPath('bloodTypes.0', 'O+')
            ->assertJsonPath('suffixes.0', 'Jr.')
            ->assertJsonPath('deferralReasons.0', 'Underweight');

        $response = $this->withToken($token)
            ->postJson('/api/donors', [
                'last_name' => 'Santos',
                'first_name' => 'Ana',
                'middle_name' => 'Marie',
                'suffix' => 'Jr.',
                'contact' => '09123456789',
                'mbd_date' => 'WI 10/20/2026',
                'birthdate' => '1997-03-10',
                'age' => 29,
                'gender' => 'Female',
                'blood_type' => 'O+',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'status' => 'passed',
                'serial_number' => 'BAG-2026-001',
                'reserved_for_px' => 'Maria Patient',
            ]);

        $response->assertCreated()
            ->assertJsonPath('donor.name', 'SANTOS, ANA MARIE JR.');

        $donor = Donor::query()->where('full_name', 'SANTOS, ANA MARIE JR.')->firstOrFail();
        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'first_name' => 'Ana',
            'middle_name' => 'Marie',
            'last_name' => 'Santos',
            'suffix' => 'Jr.',
            'province' => 'Dinagat Islands',
        ]);
        $this->assertSame('1997-03-10', $donor->birthdate->toDateString());
        $this->assertSame('2026-10-20', $donor->registered_date->toDateString());
        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'location' => 'Poblacion, Tubajon, Dinagat Islands',
            'mbd_date' => 'WI 10/20/2026',
            'serial_number' => 'BAG-2026-001',
            'reserved_for_px' => 'Maria Patient',
            'status' => 'passed',
            'bags' => 1,
        ]);
        $this->assertSame('2026-10-20', $donor->donations()->firstOrFail()->donated_on->toDateString());

        $this->withToken($token)
            ->postJson("/api/donors/{$donor->id}/donations", [
                'mbd_type' => 'MBD',
                'mbd_on' => '2026-11-08',
            ])
            ->assertCreated()
            ->assertJsonPath('donation.location', 'MBD');

        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'mbd_date' => 'MBD 11/08/2026',
            'location' => 'MBD',
        ]);
    }

    public function test_mobile_first_time_donor_registration_does_not_require_birthdate(): void
    {
        User::factory()->create([
            'username' => 'mobile-no-birthdate-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-no-birthdate-staff',
            'password' => 'secret123',
        ])->json('token');

        $response = $this->withToken($token)
            ->postJson('/api/donors', [
                'last_name' => 'Reyes',
                'first_name' => 'Ana',
                'contact' => '09123456780',
                'mbd_date' => 'MBD 11/10/2026',
                'age' => 28,
                'gender' => 'Female',
                'blood_type' => 'O+',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'status' => 'passed',
                'serial_number' => 'BAG-2026-002',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('donors', [
            'full_name' => 'REYES, ANA',
            'birthdate' => null,
        ]);
    }

    public function test_mobile_first_time_donor_registration_saves_custom_deferral_reason(): void
    {
        User::factory()->create([
            'username' => 'mobile-deferred-donor-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-deferred-donor-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/donors', [
                'last_name' => 'Reyes',
                'first_name' => 'Noel',
                'contact' => '09123456780',
                'mbd_date' => 'WI 10/20/2026',
                'birthdate' => '1990-01-01',
                'age' => 36,
                'gender' => 'Male',
                'blood_type' => 'A+',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'status' => 'deferred',
                'reason_for_deferral' => 'Custom Reason',
                'reason_for_deferral_custom' => 'Temporary medication',
            ])
            ->assertCreated()
            ->assertJsonPath('donor.name', 'REYES, NOEL');

        $donor = Donor::query()->where('full_name', 'REYES, NOEL')->firstOrFail();
        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'status' => 'deferred',
            'reason_for_deferral' => 'Temporary medication',
            'serial_number' => null,
            'bags' => 0,
        ]);
    }

    public function test_mobile_first_time_donor_registration_requires_serial_for_a_passed_donor(): void
    {
        User::factory()->create([
            'username' => 'mobile-invalid-donor-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-invalid-donor-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/donors', [
                'last_name' => 'Santos',
                'first_name' => 'Ana',
                'contact' => '09123456789',
                'mbd_date' => 'WI 10/20/2026',
                'birthdate' => '1997-03-10',
                'age' => 29,
                'gender' => 'Female',
                'blood_type' => 'O+',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'status' => 'passed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);

        $this->assertDatabaseCount('donors', 0);
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_mobile_donor_detail_returns_profile_screening_fields_and_complete_donation_history(): void
    {
        User::factory()->create([
            'username' => 'mobile-donor-detail-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-donor-detail-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Avelino Carabio',
            'last_name' => 'Carabio',
            'first_name' => 'Avelino',
            'middle_name' => 'E',
            'blood_type' => 'O+',
            'age' => 54,
            'gender' => 'Male',
            'birthdate' => '1970-07-27',
            'barangay' => 'Geotina',
            'municipality' => 'Basilisa',
            'province' => 'Dinagat Islands',
            'contact' => '09671329753',
            'registered_date' => '2025-01-01',
        ]);
        $donor->donations()->create([
            'donated_on' => '2025-01-01',
            'blood_type' => 'O+',
            'location' => 'CRH',
            'bags' => 1,
            'mbd_date' => 'WI 1/01/2025',
            'serial_number' => '20240041595',
            'status' => 'passed',
            'reserved_for_px' => 'Patient A',
            'remarks' => 'Mobile API detail record',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-02-14',
            'blood_type' => 'A+',
            'location' => 'CRH',
            'bags' => 2,
            'mbd_date' => 'MBD 02/14/2026, Surigao City',
            'serial_number' => '20260001234',
            'status' => 'deferred',
            'reason_for_deferral' => 'Low hemoglobin',
        ]);

        $this->withToken($token)
            ->getJson("/api/donors/{$donor->id}")
            ->assertOk()
            ->assertJsonPath('donor.birthdate', 'July 27, 1970')
            ->assertJsonPath('donor.registered_date', 'January 1, 2025')
            ->assertJsonPath('donor.address', 'Geotina, Basilisa, Dinagat Islands')
            ->assertJsonPath('donor.donation_count', 2)
            ->assertJsonPath('donor.latest_donation.mbd_date', 'MBD 02/14/2026, Surigao City')
            ->assertJsonPath('donor.latest_donation.status', 'Deferred')
            ->assertJsonPath('donor.latest_donation.serial_number', '20260001234')
            ->assertJsonPath('donor.latest_donation.reason_for_deferral', 'Low hemoglobin')
            ->assertJsonPath('donor.donations.0.date', 'February 14, 2026')
            ->assertJsonPath('donor.donations.0.blood_type', 'A+')
            ->assertJsonPath('donor.donations.0.bags', 2)
            ->assertJsonPath('donor.donations.0.status', 'Deferred')
            ->assertJsonPath('donor.donations.1.status', 'Passed');
    }

    public function test_mobile_location_search_returns_matching_donors_from_the_selected_year(): void
    {
        User::factory()->create([
            'username' => 'mobile-location-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-location-staff',
            'password' => 'secret123',
        ])->json('token');

        $createDonor = function (string $name, string $municipality, string $donationLocation, string $date, string $status): Donor {
            $donor = Donor::query()->create([
                'full_name' => $name,
                'blood_type' => 'O+',
                'age' => 28,
                'gender' => 'Male',
                'barangay' => 'Poblacion',
                'municipality' => $municipality,
                'province' => 'Surigao del Norte',
                'contact' => '09180000002',
                'registered_date' => $date,
            ]);

            $donor->donations()->create([
                'donated_on' => $date,
                'blood_type' => 'O+',
                'location' => $donationLocation,
                'bags' => 1,
                'status' => $status,
            ]);

            return $donor;
        };

        $addressMatch = $createDonor('Address Match', 'Surigao City', 'CRH', '2026-02-10', 'passed');
        $venueMatch = $createDonor('Venue Match', 'Tubajon', 'Surigao City Collection', '2026-03-10', 'passed');
        $createDonor('Old Record', 'Surigao City', 'CRH', '2025-02-10', 'passed');
        $createDonor('Deferred Record', 'Surigao City', 'CRH', '2026-04-10', 'deferred');

        $this->withToken($token)
            ->getJson('/api/donors/search-by-location?location=surigao&year=2026')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['id' => $addressMatch->id, 'name' => 'Address Match'])
            ->assertJsonFragment(['id' => $venueMatch->id, 'name' => 'Venue Match']);
    }

    public function test_mobile_location_search_rejects_an_invalid_year(): void
    {
        User::factory()->create([
            'username' => 'mobile-invalid-year-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-invalid-year-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/donors/search-by-location?location=Surigao&year=2200')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_mobile_edit_updates_a_donor_profile(): void
    {
        User::factory()->create([
            'username' => 'mobile-edit-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-edit-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Original Donor',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Male',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000004',
            'registered_date' => '2026-01-10',
        ]);

        $this->withToken($token)
            ->patchJson("/api/donors/{$donor->id}", [
                'full_name' => 'Updated Donor',
                'blood_type' => 'A+',
                'age' => 35,
                'gender' => 'Female',
                'contact' => '09180000005',
                'barangay' => 'Washington',
                'municipality' => 'Surigao City',
                'province' => 'Surigao del Norte',
            ])
            ->assertOk()
            ->assertJsonPath('donor.name', 'Updated Donor');

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'full_name' => 'Updated Donor',
            'blood_type' => 'A+',
            'age' => 35,
            'gender' => 'Female',
            'contact' => '09180000005',
            'barangay' => 'Washington',
            'municipality' => 'Surigao City',
            'first_name' => null,
            'last_name' => null,
        ]);
    }

    public function test_mobile_donor_edit_updates_name_parts_and_latest_screening_record(): void
    {
        User::factory()->create([
            'username' => 'mobile-screening-edit-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);
        $token = $this->postJson('/api/login', [
            'username' => 'mobile-screening-edit-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'SANTOS, ANA MARIE JR.',
            'first_name' => 'Ana',
            'middle_name' => 'Marie',
            'last_name' => 'Santos',
            'suffix' => 'Jr.',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Female',
            'birthdate' => '1997-03-10',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000004',
            'registered_date' => '2026-01-10',
        ]);
        $donation = $donor->donations()->create([
            'donated_on' => '2026-01-10',
            'blood_type' => 'O+',
            'location' => 'WI',
            'mbd_date' => 'WI 1/10/2026',
            'status' => 'passed',
            'serial_number' => 'BAG-OLD-001',
            'reserved_for_px' => 'Old Patient',
            'bags' => 1,
        ]);

        $this->withToken($token)
            ->patchJson("/api/donors/{$donor->id}", [
                'first_name' => 'Ana',
                'middle_name' => 'Marie',
                'last_name' => 'Reyes',
                'suffix' => 'Sr.',
                'blood_type' => 'A+',
                'age' => 29,
                'gender' => 'Female',
                'contact' => '09180000005',
                'barangay' => 'Washington',
                'municipality' => 'Surigao City',
                'province' => 'Surigao del Norte',
                'mbd_date' => 'WI 2/10/2026',
                'status' => 'passed',
                'serial_number' => 'BAG-NEW-001',
                'reserved_for_px' => 'New Patient',
                'reason_for_deferral' => '',
            ])
            ->assertOk()
            ->assertJsonPath('donor.name', 'REYES, ANA MARIE SR.');

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'suffix' => 'Sr.',
            'blood_type' => 'A+',
            'birthdate' => '1997-03-10 00:00:00',
        ]);
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'donated_on' => '2026-02-10 00:00:00',
            'mbd_date' => 'WI 2/10/2026',
            'serial_number' => 'BAG-NEW-001',
            'reserved_for_px' => 'New Patient',
            'blood_type' => 'A+',
            'status' => 'passed',
        ]);

        $this->withToken($token)
            ->getJson("/api/donors/{$donor->id}")
            ->assertOk()
            ->assertJsonPath('donor.first_name', 'Ana')
            ->assertJsonPath('donor.last_name', 'Reyes')
            ->assertJsonPath('donor.latest_donation.serial_number', 'BAG-NEW-001');
    }

    public function test_mobile_edit_preserves_name_parts_when_the_donor_name_is_unchanged(): void
    {
        User::factory()->create([
            'username' => 'mobile-name-parts-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-name-parts-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Ava Marie Santos',
            'first_name' => 'Ava',
            'middle_name' => 'Marie',
            'last_name' => 'Santos',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Female',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000008',
            'registered_date' => '2026-01-10',
        ]);

        $this->withToken($token)
            ->patchJson("/api/donors/{$donor->id}", [
                'full_name' => 'Ava Marie Santos',
                'blood_type' => 'O+',
                'age' => 29,
                'gender' => 'Female',
                'contact' => '09180000008',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
            ])
            ->assertOk()
            ->assertJsonPath('donor.name', 'SANTOS, AVA MARIE');

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'first_name' => 'Ava',
            'middle_name' => 'Marie',
            'last_name' => 'Santos',
        ]);
    }

    public function test_mobile_edit_rejects_an_incomplete_donor_profile_without_changing_it(): void
    {
        User::factory()->create([
            'username' => 'mobile-invalid-edit-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-invalid-edit-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Unchanged Donor',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Male',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000006',
            'registered_date' => '2026-01-10',
        ]);

        $this->withToken($token)
            ->patchJson("/api/donors/{$donor->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name',
                'blood_type',
                'age',
                'gender',
                'contact',
                'barangay',
                'municipality',
            ]);

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'full_name' => 'Unchanged Donor',
            'blood_type' => 'O+',
        ]);
    }

    public function test_mobile_edit_rejects_a_name_already_used_by_another_donor(): void
    {
        User::factory()->create([
            'username' => 'mobile-duplicate-name-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-duplicate-name-staff',
            'password' => 'secret123',
        ])->json('token');

        $existingDonor = Donor::query()->create([
            'full_name' => 'Existing Donor',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Male',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000009',
            'registered_date' => '2026-01-10',
        ]);
        $donor = Donor::query()->create([
            'full_name' => 'Donor To Edit',
            'blood_type' => 'A+',
            'age' => 30,
            'gender' => 'Female',
            'barangay' => 'Washington',
            'municipality' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'contact' => '09180000010',
            'registered_date' => '2026-01-10',
        ]);

        $this->withToken($token)
            ->patchJson("/api/donors/{$donor->id}", [
                'full_name' => $existingDonor->full_name,
                'blood_type' => 'A+',
                'age' => 30,
                'gender' => 'Female',
                'contact' => '09180000010',
                'barangay' => 'Washington',
                'municipality' => 'Surigao City',
                'province' => 'Surigao del Norte',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', 'A donor with this name already exists.');

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'full_name' => 'Donor To Edit',
        ]);
    }

    public function test_mobile_delete_removes_the_donor_and_their_donations(): void
    {
        User::factory()->create([
            'username' => 'mobile-single-delete-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-single-delete-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Delete This Donor',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Male',
            'barangay' => 'Poblacion',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'contact' => '09180000007',
            'registered_date' => '2026-01-10',
        ]);
        $donation = $donor->donations()->create([
            'donated_on' => '2026-01-10',
            'blood_type' => 'O+',
            'location' => 'CRH',
            'bags' => 1,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/donors/{$donor->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Donor deleted.');

        $this->assertDatabaseMissing('donors', ['id' => $donor->id]);
        $this->assertDatabaseMissing('donations', ['id' => $donation->id]);
    }

    public function test_mobile_delete_all_removes_donors_and_their_donations(): void
    {
        User::factory()->create([
            'username' => 'mobile-delete-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-delete-staff',
            'password' => 'secret123',
        ])->json('token');

        $donors = collect(['First Donor', 'Second Donor'])->map(function (string $name): Donor {
            $donor = Donor::query()->create([
                'full_name' => $name,
                'blood_type' => 'O+',
                'age' => 28,
                'gender' => 'Female',
                'barangay' => 'Poblacion',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'contact' => '09180000003',
                'registered_date' => '2026-01-10',
            ]);

            $donor->donations()->create([
                'donated_on' => '2026-01-10',
                'blood_type' => 'O+',
                'location' => 'CRH',
                'bags' => 1,
            ]);

            return $donor;
        });

        $this->withToken($token)
            ->deleteJson('/api/donors')
            ->assertOk()
            ->assertJsonPath('total_deleted', 2);

        foreach ($donors as $donor) {
            $this->assertDatabaseMissing('donors', ['id' => $donor->id]);
            $this->assertDatabaseMissing('donations', ['donor_id' => $donor->id]);
        }
    }

    public function test_mobile_collection_search_and_delete_all_require_authentication(): void
    {
        $this->getJson('/api/donors/search-by-location?year=2026')
            ->assertUnauthorized();

        $this->deleteJson('/api/donors')
            ->assertUnauthorized();

        $this->patchJson('/api/donors/1', [])
            ->assertUnauthorized();

        $this->deleteJson('/api/donors/1')
            ->assertUnauthorized();
    }

    public function test_invalid_login_returns_validation_error(): void
    {
        $this->postJson('/api/login', [
            'username' => 'nobody',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_authenticated_staff_can_load_reports_and_consumption_meta(): void
    {
        $user = User::factory()->create([
            'username' => 'apistaff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'apistaff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/reports?view=periodic&period=monthly&year=2026')
            ->assertOk()
            ->assertJsonStructure([
                'view',
                'periodic',
                'ageReport',
                'gender',
                'consumption',
                'consumptionDemographics' => [
                    'age' => ['totalPatients', 'groups'],
                    'gender' => ['totalPatients', 'rows'],
                ],
            ])
            ->assertJsonPath('periodicYears', [2024, 2025, 2026, 2027]);

        $this->withToken($token)
            ->getJson('/api/reports?view=periodic&period=yearly&year=2027')
            ->assertOk()
            ->assertJsonPath('periodic.rows.0.label', '2024')
            ->assertJsonPath('periodic.rows.3.label', '2027');

        $this->withToken($token)
            ->getJson('/api/consumption/meta')
            ->assertOk()
            ->assertJsonStructure([
                'components',
                'provinces',
                'municipalitiesByProvince',
                'places',
                'months',
                'years',
            ])
            ->assertJsonPath('municipalitiesByProvince.Surigao del Norte.4', 'Dapa');
    }

    public function test_mobile_print_all_reports_includes_full_collection_and_consumption_records(): void
    {
        User::factory()->create([
            'username' => 'mobile-print-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-print-staff',
            'password' => 'secret123',
        ])->json('token');

        $donor = Donor::query()->create([
            'full_name' => 'Mobile Print Donor',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'barangay' => 'Central',
            'municipality' => 'Placer',
            'province' => 'Surigao del Norte',
            'contact' => '09180000041',
            'registered_date' => '2026-01-10',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-01-10',
            'blood_type' => 'O+',
            'location' => 'CRH',
            'bags' => 2,
        ]);

        foreach ([
            ['patient_name' => 'Older Print Patient', 'date' => '2025-12-20', 'month' => 12, 'year' => 2025, 'units' => 2, 'blood_type' => 'O-'],
            ['patient_name' => 'Current Print Patient', 'date' => '2026-04-12', 'month' => 4, 'year' => 2026, 'units' => 1, 'blood_type' => 'A+'],
        ] as $record) {
            ConsumptionRecord::query()->create([
                'patient_name' => $record['patient_name'],
                'age' => 31,
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'province' => 'Surigao del Norte',
                'municipality' => 'Placer',
                'barangay' => 'Central',
                'location' => 'Central, Placer, Surigao del Norte',
                'blood_type' => $record['blood_type'],
                'number_of_bags' => $record['units'],
                'month' => $record['month'],
                'year' => $record['year'],
                'reporting_period' => $record['year'].'-'.$record['month'],
                'date_submitted' => $record['date'],
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/reports?view=demographics&period=monthly&year=2026&consumption_year=2026&consumption_month=4&blood_type=A%2B&print_all=1')
            ->assertOk()
            ->assertJsonPath('printAll', true)
            ->assertJsonPath('printPeriods.monthly.rows.0.label', 'January')
            ->assertJsonPath('printPeriods.quarterly.rows.0.label', 'Q1')
            ->assertJsonPath('printPeriods.yearly.rows.0.label', '2026')
            ->assertJsonPath('periodic.rows.0.records.0.name', 'Mobile Print Donor')
            ->assertJsonPath('ageReport.groups.0.donors.0.name', 'Mobile Print Donor')
            ->assertJsonPath('gender.rows.0.donors.0.name', 'Mobile Print Donor')
            ->assertJsonPath('consumptionDemographics.age.totalPatients', 1)
            ->assertJsonPath('consumption.totalUnits', 1)
            ->assertJsonPath('consumption.provinces.0.municipalities.0.records.0.patient_name', 'Current Print Patient');
    }

    public function test_mobile_demographics_reports_separate_unique_consumption_patients_from_collection_donors(): void
    {
        $user = User::factory()->create([
            'username' => 'mobile-report-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'mobile-report-staff',
            'password' => 'secret123',
        ])->json('token');

        foreach ([
            [
                'patient_name' => 'Ava Santos',
                'age' => 24,
                'sex' => 'F',
                'number_of_bags' => 2,
                'month' => 6,
                'year' => 2026,
            ],
            [
                'patient_name' => 'ava santos',
                'age' => 24,
                'sex' => 'f',
                'number_of_bags' => 3,
                'month' => 6,
                'year' => 2026,
            ],
            [
                'patient_name' => 'Miguel Reyes',
                'age' => 34,
                'sex' => 'M',
                'number_of_bags' => 4,
                'month' => 6,
                'year' => 2026,
            ],
            [
                'patient_name' => 'Older Record',
                'age' => 45,
                'sex' => 'F',
                'number_of_bags' => 7,
                'month' => 6,
                'year' => 2025,
            ],
        ] as $record) {
            ConsumptionRecord::query()->create([
                ...$record,
                'location' => 'Surigao City',
                'blood_type' => 'O+',
                'blood_component' => 'Packed RBC',
                'province' => 'Surigao del Norte',
                'municipality' => 'Surigao City',
                'barangay' => 'Washington',
                'reporting_period' => 'June '.$record['year'],
                'date_submitted' => sprintf('%04d-%02d-28', $record['year'], $record['month']),
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/reports?view=demographics&year=2026&month=all&consumption_year=2026&consumption_month=all')
            ->assertOk()
            ->assertJsonPath('ageReport.totalDonors', 0)
            ->assertJsonPath('consumptionDemographics.age.totalPatients', 2)
            ->assertJsonPath('consumptionDemographics.age.groups.0.count', 1)
            ->assertJsonPath('consumptionDemographics.age.groups.0.bags', 5)
            ->assertJsonPath('consumptionDemographics.age.groups.1.count', 1)
            ->assertJsonPath('consumptionDemographics.gender.rows.0.count', 1)
            ->assertJsonPath('consumptionDemographics.gender.rows.0.bags', 5)
            ->assertJsonPath('consumptionDemographics.gender.rows.1.count', 1)
            ->assertJsonPath('consumptionDemographics.gender.rows.1.bags', 4);
    }

    public function test_api_consumption_is_assigned_to_its_mapped_province_for_reports(): void
    {
        User::factory()->create([
            'username' => 'consumptionstaff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumptionstaff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'date_submitted' => '2026-04-12',
                'patient_name' => 'API Patient',
                'age' => 30,
                'birthdate' => '1996-04-12',
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'units' => 2,
                'province' => 'Surigao del Norte',
                'municipality' => 'Placer',
                'barangay' => 'Central (Poblacion)',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('consumption_records', [
            'patient_name' => 'API PATIENT',
            'province' => 'Surigao del Norte',
            'location' => 'Central (poblacion), Placer, Surigao del Norte',
            'birthdate' => '1996-04-12 00:00:00',
        ]);

        $this->withToken($token)
            ->getJson('/api/consumption')
            ->assertOk()
            ->assertJsonPath('records.0.birthdate', '1996-04-12');

        $report = app(ReportService::class)->consumption('Surigao del Norte', '', '4', '2026');
        $province = $report['provinces']->firstWhere('name', 'Surigao del Norte');

        $this->assertIsArray($province);
        $this->assertSame(2, $province['total_units']);
        $this->assertSame('API PATIENT', $province['municipalities'][0]['records'][0]['patient_name']);
    }

    public function test_mobile_consumption_rejects_a_municipality_outside_the_selected_province(): void
    {
        User::factory()->create([
            'username' => 'consumption-location-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-location-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'date_submitted' => '2026-04-12',
                'patient_name' => 'Location Test Patient',
                'age' => 30,
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'units' => 1,
                'province' => 'Agusan del Sur',
                'municipality' => 'Placer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['municipality']);

        $this->assertDatabaseCount('consumption_records', 0);
    }

    public function test_mobile_consumption_rejects_a_birthdate_after_today(): void
    {
        User::factory()->create([
            'username' => 'consumption-birthdate-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-birthdate-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'date_submitted' => '2026-04-12',
                'patient_name' => 'Future Birthday',
                'age' => 30,
                'birthdate' => now()->addDay()->toDateString(),
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'units' => 1,
                'province' => 'Surigao del Norte',
                'municipality' => 'Placer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birthdate']);

        $this->assertDatabaseCount('consumption_records', 0);
    }

    public function test_mobile_consumption_meta_includes_saved_barangay_choices_for_other_provinces(): void
    {
        User::factory()->create([
            'username' => 'consumption-meta-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Existing Patient',
            'age' => 30,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Bayugan City',
            'province' => 'Agusan del Sur',
            'barangay' => 'Poblacion',
            'location' => 'Poblacion, Bayugan City, Agusan del Sur',
            'number_of_bags' => 1,
            'month' => 4,
            'year' => 2026,
            'reporting_period' => 'April 2026',
            'date_submitted' => '2026-04-12',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-meta-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/consumption/meta')
            ->assertOk()
            ->assertJsonPath('places.Bayugan City.0', 'Poblacion');

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'date_submitted' => '2026-04-13',
                'patient_name' => 'Agusan Patient',
                'age' => 34,
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'units' => 1,
                'province' => 'Agusan del Sur',
                'municipality' => 'Bayugan City',
                'barangay' => 'Poblacion',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('consumption_records', [
            'patient_name' => 'AGUSAN PATIENT',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'barangay' => 'Poblacion',
        ]);
    }

    public function test_mobile_consumption_filters_by_province_and_returns_distinct_patient_names_and_component_totals(): void
    {
        User::factory()->create([
            'username' => 'consumption-filter-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-filter-staff',
            'password' => 'secret123',
        ])->json('token');

        $createRecord = function (string $patientName, int $units, string $municipality, string $province): void {
            ConsumptionRecord::query()->create([
                'location' => "{$municipality}, {$province}",
                'patient_name' => $patientName,
                'age' => 34,
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'municipality' => $municipality,
                'province' => $province,
                'barangay' => 'Poblacion',
                'blood_type' => 'O+',
                'number_of_bags' => $units,
                'month' => 4,
                'year' => 2026,
                'reporting_period' => 'April 2026',
                'date_submitted' => '2026-04-12',
            ]);
        };

        $createRecord('  Maria   Santos ', 2, 'Tubajon', 'Dinagat Islands');
        $createRecord('MARIA SANTOS', 3, 'Tubajon', 'Dinagat Islands');
        $createRecord('Another Patient', 1, 'Surigao City', 'Surigao del Norte');

        $this->withToken($token)
            ->getJson('/api/consumption?province=Dinagat%20Islands&q=Maria')
            ->assertOk()
            ->assertJsonPath('totalUnits', 5)
            ->assertJsonPath('totalsByComponent.3.total', 5)
            ->assertJsonPath('matchTotal', 1)
            ->assertJsonPath('patients', ['MARIA SANTOS'])
            ->assertJsonPath('filters.province', 'Dinagat Islands');
    }

    public function test_mobile_consumption_patient_history_groups_name_format_variations(): void
    {
        User::factory()->create([
            'username' => 'consumption-history-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-history-staff',
            'password' => 'secret123',
        ])->json('token');

        foreach (['Maria Santos', '  MARIA   SANTOS '] as $patientName) {
            ConsumptionRecord::query()->create([
                'location' => 'Tubajon, Dinagat Islands',
                'patient_name' => $patientName,
                'age' => 34,
                'birthdate' => '1994-06-08',
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'municipality' => 'Tubajon',
                'province' => 'Dinagat Islands',
                'barangay' => 'Poblacion',
                'blood_type' => 'O+',
                'number_of_bags' => 1,
                'month' => 4,
                'year' => 2026,
                'reporting_period' => 'April 2026',
                'date_submitted' => '2026-04-12',
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/consumption?details=1&patient=MARIA%20SANTOS&province=Dinagat%20Islands')
            ->assertOk()
            ->assertJsonPath('patient', 'MARIA SANTOS')
            ->assertJsonCount(2, 'visits')
            ->assertJsonPath('visits.0.birthdate', '1994-06-08');
    }

    public function test_mobile_consumption_delete_all_requires_authentication_and_removes_all_records(): void
    {
        ConsumptionRecord::query()->create([
            'location' => 'Tubajon, Dinagat Islands',
            'patient_name' => 'Patient One',
            'age' => 34,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'barangay' => 'Poblacion',
            'blood_type' => 'O+',
            'number_of_bags' => 1,
            'month' => 4,
            'year' => 2026,
            'reporting_period' => 'April 2026',
            'date_submitted' => '2026-04-12',
        ]);

        $this->deleteJson('/api/consumption')->assertUnauthorized();
        $this->assertDatabaseCount('consumption_records', 1);

        User::factory()->create([
            'username' => 'consumption-delete-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);

        $token = $this->postJson('/api/login', [
            'username' => 'consumption-delete-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->deleteJson('/api/consumption')
            ->assertOk()
            ->assertJsonPath('deletedCount', 1);

        $this->assertDatabaseCount('consumption_records', 0);
    }

    public function test_mobile_consumption_records_can_be_updated_with_web_reporting_fields(): void
    {
        User::factory()->create([
            'username' => 'consumption-edit-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);
        $token = $this->postJson('/api/login', [
            'username' => 'consumption-edit-staff',
            'password' => 'secret123',
        ])->json('token');
        $record = ConsumptionRecord::query()->create([
            'location' => 'Tubajon, Dinagat Islands',
            'patient_name' => 'OLD PATIENT',
            'age' => 34,
            'birthdate' => '1992-04-12',
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Tubajon',
            'province' => 'Dinagat Islands',
            'barangay' => 'Poblacion',
            'blood_type' => 'O+',
            'number_of_bags' => 1,
            'month' => 4,
            'year' => 2026,
            'reporting_period' => 'April 2026',
            'date_submitted' => '2026-04-12',
        ]);

        $this->withToken($token)
            ->patchJson("/api/consumption/{$record->id}", [
                'patient_name' => 'UPDATED PATIENT',
                'date_submitted' => '2026-05-03 00:00:00',
                'age' => 42,
                'sex' => 'M',
                'blood_component' => 'Fresh Frozen Plasma',
                'units' => 3,
                'province' => 'Surigao del Norte',
                'municipality' => 'Surigao City',
                'barangay' => 'Washington',
            ])
            ->assertOk()
            ->assertJsonPath('record.patient_name', 'UPDATED PATIENT');

        $this->assertDatabaseHas('consumption_records', [
            'id' => $record->id,
            'patient_name' => 'UPDATED PATIENT',
            'date_submitted' => '2026-05-03 00:00:00',
            'age' => 42,
            'birthdate' => '1992-04-12 00:00:00',
            'sex' => 'M',
            'blood_component' => 'Fresh Frozen Plasma',
            'number_of_bags' => 3,
            'month' => 5,
            'year' => 2026,
            'reporting_period' => 'May 2026',
            'province' => 'Surigao del Norte',
            'municipality' => 'Surigao City',
            'barangay' => 'Washington',
        ]);
    }

    public function test_mobile_returning_consumption_search_and_store_reuse_latest_patient_details(): void
    {
        User::factory()->create([
            'username' => 'returning-consumption-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);
        $token = $this->postJson('/api/login', [
            'username' => 'returning-consumption-staff',
            'password' => 'secret123',
        ])->json('token');
        ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 31,
            'birthdate' => '1995-03-10',
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (Poblacion), Placer, Surigao del Norte',
            'blood_type' => 'A+',
            'number_of_bags' => 1,
            'month' => 4,
            'year' => 2026,
            'reporting_period' => 'April 2026',
            'date_submitted' => '2026-04-12',
        ]);
        $today = now();

        $this->withToken($token)
            ->getJson('/api/consumption/returning-patients?q=a')
            ->assertOk()
            ->assertJsonPath('patients', ['ANA REYES']);

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'patient_mode' => 'returning',
                'returning_patient_name' => 'ANA REYES',
                'blood_component' => 'Fresh Frozen Plasma',
                'units' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('record.patient_name', 'ANA REYES');

        $record = ConsumptionRecord::query()->whereDate('date_submitted', $today)->orderByDesc('id')->firstOrFail();
        $this->assertSame(31, $record->age);
        $this->assertSame('1995-03-10', $record->birthdate->toDateString());
        $this->assertSame('F', $record->sex);
        $this->assertSame('Fresh Frozen Plasma', $record->blood_component);
        $this->assertSame(2, $record->number_of_bags);
        $this->assertSame('Surigao del Norte', $record->province);
        $this->assertSame('Placer', $record->municipality);
        $this->assertSame('Central (Poblacion)', $record->barangay);
        $this->assertSame('A+', $record->blood_type);
        $this->assertSame('Central (Poblacion), Placer, Surigao del Norte', $record->location);
        $this->assertSame($today->toDateString(), $record->date_submitted->toDateString());
        $this->assertSame((int) $today->month, $record->month);
        $this->assertSame((int) $today->year, $record->year);
        $this->assertSame($today->format('F Y'), $record->reporting_period);
    }

    public function test_mobile_returning_consumption_rejects_a_patient_without_existing_records(): void
    {
        User::factory()->create([
            'username' => 'returning-consumption-invalid-staff',
            'password' => 'secret123',
            'status' => 'Active',
        ]);
        $token = $this->postJson('/api/login', [
            'username' => 'returning-consumption-invalid-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/consumption', [
                'patient_mode' => 'returning',
                'returning_patient_name' => 'UNKNOWN PATIENT',
                'blood_component' => 'Packed RBC',
                'units' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['returning_patient_name']);

        $this->assertDatabaseCount('consumption_records', 0);
    }

    public function test_mobile_staff_can_update_profile_and_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'username' => 'profile-edit-staff',
            'password' => 'secret123',
            'email' => 'profile-edit-staff@staff.local',
            'status' => 'Active',
        ]);
        $token = $this->postJson('/api/login', [
            'username' => 'profile-edit-staff',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)
            ->patchJson('/api/profile', [
                'name' => 'Updated Staff Name',
                'email' => 'updated.staff@example.com',
                'contact' => '09181112222',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Staff Name')
            ->assertJsonPath('user.email', 'updated.staff@example.com')
            ->assertJsonPath('user.contact', '09181112222')
            ->assertJsonPath('user.username', 'profile-edit-staff');

        $this->withToken($token)
            ->patchJson('/api/profile/password', [
                'current_password' => 'incorrect',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->withToken($token)
            ->patchJson('/api/profile/password', [
                'current_password' => 'secret123',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Staff Name',
            'email' => 'updated.staff@example.com',
            'contact' => '09181112222',
        ]);
        $this->assertTrue(Hash::check('newsecret123', $user->fresh()->password));
    }
}
