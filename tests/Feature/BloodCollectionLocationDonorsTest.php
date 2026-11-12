<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BloodCollectionLocationDonorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_location_year_lists_donors_by_municipality_up_to_one_hundred(): void
    {
        $this->actingAs(User::factory()->create());

        $placer = Donor::query()->create([
            'full_name' => 'Ana Cruz',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'barangay' => 'Central (Poblacion)',
            'municipality' => 'Placer',
            'province' => null,
            'contact' => '09180000001',
            'registered_date' => '2026-01-10',
        ]);
        $placer->donations()->create([
            'donated_on' => '2026-02-15',
            'blood_type' => 'O+',
            'location' => 'Placer',
            'bags' => 1,
        ]);

        $city = Donor::query()->create([
            'full_name' => 'Ben Santos',
            'blood_type' => 'A+',
            'age' => 25,
            'gender' => 'Male',
            'municipality' => 'Surigao City',
            'barangay' => 'Washington',
            'contact' => '09180000002',
            'registered_date' => '2026-03-01',
        ]);
        $city->donations()->create([
            'donated_on' => '2026-03-10',
            'blood_type' => 'A+',
            'location' => 'Surigao City',
            'bags' => 1,
        ]);

        $this->get('/dashboard/blood-collection?mode=location-year&year=2026')
            ->assertOk()
            ->assertSee('Ana Cruz', false)
            ->assertSee('Ben Santos', false);

        $this->get('/dashboard/blood-collection?mode=location-year&year=2026&municipality=Placer')
            ->assertOk()
            ->assertSee('Ana Cruz', false)
            ->assertDontSee('Ben Santos', false);

        $surigaoReport = app(ReportService::class)->monthly(2, 2026, 'Surigao del Norte');
        $this->assertSame(1, $surigaoReport['uniqueDonors']);
        $this->assertSame('Central (poblacion), Placer, Surigao del Norte', $surigaoReport['donations'][0]['location']);
    }

    public function test_new_donor_is_saved_and_reported_under_municipality_province(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/dashboard/blood-collection/donors', [
            'full_name' => 'New Placer Donor',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'contact' => '09180000003',
            'barangay' => 'Central (Poblacion)',
            'municipality' => 'Placer',
            'province' => '',
            'initial_donation_date' => '2026-04-12',
            'donation_location' => 'Placer Health Center',
        ])->assertRedirect();

        $this->assertDatabaseHas('donors', [
            'full_name' => 'New Placer Donor',
            'province' => 'Surigao del Norte',
        ]);

        $report = app(ReportService::class)->monthly(4, 2026, 'Surigao del Norte');
        $this->assertSame(1, $report['uniqueDonors']);
        $this->assertSame('Central (poblacion), Placer, Surigao del Norte', $report['donations'][0]['location']);

        $this->get('/dashboard/reports?view=periodic&period=monthly&year=2026&month=4&location=Surigao%20del%20Norte')
            ->assertOk()
            ->assertSee('New Placer Donor', false)
            ->assertSee('Central (poblacion), Placer, Surigao del Norte', false)
            ->assertDontSee('Monthly Donor Report', false)
            ->assertDontSee('Donor Records List', false);
    }

    public function test_returning_donor_search_matches_after_one_letter(): void
    {
        $this->actingAs(User::factory()->create());

        $donor = Donor::query()->create([
            'full_name' => 'Ana Cruz',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'barangay' => 'Central',
            'municipality' => 'Placer',
            'province' => 'Surigao del Norte',
            'contact' => '09180000006',
            'registered_date' => '2026-01-10',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-01-10',
            'blood_type' => 'O+',
            'location' => 'CRH',
            'bags' => 1,
        ]);

        $this->getJson('/dashboard/blood-collection?lookup=returning&q=a')
            ->assertOk()
            ->assertJsonPath('0.name', 'Ana Cruz');
    }

    public function test_legacy_donor_without_province_is_visible_under_inferred_province(): void
    {
        $this->actingAs(User::factory()->create());

        $donor = Donor::query()->create([
            'full_name' => 'Legacy Placer Donor',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'barangay' => 'Central (Poblacion)',
            'municipality' => 'Placer',
            'province' => null,
            'contact' => '09180000004',
            'registered_date' => '2026-04-12',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-04-12',
            'blood_type' => 'O+',
            'location' => 'Placer Health Center',
            'bags' => 1,
        ]);

        $this->get('/dashboard/reports?view=periodic&period=monthly&year=2026&month=4&location=Surigao%20del%20Norte')
            ->assertOk()
            ->assertSee('Legacy Placer Donor', false)
            ->assertSee('Central (poblacion), Placer, Surigao del Norte', false)
            ->assertDontSee('Monthly Donor Report', false)
            ->assertDontSee('Donor Records List', false);
    }

    public function test_donor_registration_and_profile_open_as_full_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $donor = Donor::query()->create([
            'full_name' => 'ANA CRUZ',
            'blood_type' => 'O+',
            'age' => 30,
            'gender' => 'Female',
            'contact' => '09180000005',
            'barangay' => 'Central (Poblacion)',
            'municipality' => 'Placer',
            'registered_date' => '2026-04-12',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-04-12',
            'blood_type' => 'O+',
            'location' => 'Placer',
            'bags' => 1,
        ]);

        $this->get('/dashboard/blood-collection?register=1')
            ->assertOk()
            ->assertSee('id="register-modal" class="page-panel"', false)
            ->assertDontSee('id="register-modal" class="modal-backdrop"', false)
            ->assertSee('Register First-Time Donor', false);

        $this->get('/dashboard/blood-collection?mode=name&donor='.$donor->id)
            ->assertOk()
            ->assertSee('class="page-panel" id="donor-modal"', false)
            ->assertDontSee('class="modal-backdrop" id="donor-modal"', false)
            ->assertSee('Complete Donation History', false)
            ->assertSee('ANA CRUZ', false);

        $this->get('/dashboard/blood-collection?mode=name&donor='.$donor->id.'&edit=1')
            ->assertOk()
            ->assertSee('class="page-panel" id="edit-donor-modal"', false)
            ->assertSee('id="editBirthdate"', false)
            ->assertSee('Edit Donor Info', false);
    }

    public function test_collection_form_saves_mbd_type_with_date_and_title_cased_name(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/blood-collection?register=1')
            ->assertOk()
            ->assertSee('name="mbd_type"', false)
            ->assertSee('name="mbd_on"', false)
            ->assertDontSee('id="regBirthdate"', false)
            ->assertSee('type="date"', false)
            ->assertSeeInOrder(['Province', 'Municipality / City', 'Barangay'], false);

        $this->post('/dashboard/blood-collection/donors', [
            'first_name' => 'maria',
            'middle_name' => 'dela',
            'last_name' => 'cruz',
            'blood_type' => 'O+',
            'age' => 28,
            'gender' => 'Female',
            'contact' => '09180000009',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'mbd_type' => 'WI',
            'mbd_on' => '2025-01-01',
            'status' => 'passed',
            'serial_number' => 'SN-100',
            'reserved_for_px' => 'juan dela cruz',
        ])->assertRedirect();

        $this->assertDatabaseHas('donors', [
            'full_name' => 'Cruz, Maria Dela',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'birthdate' => null,
        ]);
        $this->assertDatabaseHas('donations', [
            'mbd_date' => 'WI 01/01/2025',
            'reserved_for_px' => 'Juan Dela Cruz',
        ]);

        $donor = Donor::query()->where('full_name', 'Cruz, Maria Dela')->firstOrFail();
        $this->post('/dashboard/blood-collection/donors/'.$donor->id.'/donations', [
            'mbd_type' => 'MBD',
            'mbd_on' => '2026-11-08',
            'form' => 'returning-donation',
            'returning_donor' => $donor->id,
            'mode' => 'name',
        ])->assertRedirect();

        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'mbd_date' => 'MBD 11/08/2026',
            'location' => 'MBD',
        ]);

        $this->post('/dashboard/blood-collection/donors', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'blood_type' => 'A+',
            'age' => 30,
            'gender' => 'Female',
            'contact' => '09180000010',
            'province' => 'Dinagat Islands',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'mbd_type' => 'MBD',
            'mbd_on' => '2025-02-02',
            'birthdate' => '1996-02-02',
            'status' => 'deferred',
            'reason_for_deferral' => 'Underweight',
        ])->assertSessionHasErrors('municipality');
    }
}
