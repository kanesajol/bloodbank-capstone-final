<?php

namespace Tests\Feature;

use App\Models\ConsumptionRecord;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BloodConsumptionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_consumption_page_lists_patients_without_requiring_municipality(): void
    {
        $this->actingAs(User::factory()->create());

        ConsumptionRecord::query()->create([
            'patient_name' => 'Ana Reyes',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Placer',
            'location' => 'Placer',
            'number_of_bags' => 8,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Ben Santos',
            'age' => 28,
            'sex' => 'M',
            'blood_component' => 'Fresh Whole Blood',
            'municipality' => 'Surigao City',
            'location' => 'Surigao City',
            'number_of_bags' => 5,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->get('/dashboard/blood-consumption')
            ->assertOk()
            ->assertDontSee('id="filter-municipality"', false)
            ->assertDontSee('id="filter-barangay"', false)
            ->assertDontSee('id="filter-component"', false)
            ->assertDontSee('id="filter-month"', false)
            ->assertDontSee('id="filter-year"', false)
            ->assertDontSee('id="consumption-reset"', false)
            ->assertSee('id="municipality" name="municipality"', false)
            ->assertSee('id="province" name="province"', false)
            ->assertSee('id="barangay" name="barangay"', false)
            ->assertSee('>Select province</option>', false)
            ->assertSee('>Select province first</option>', false)
            ->assertSee('id="consumption-results"', false)
            ->assertSee('id="patientLastName" name="last_name"', false)
            ->assertSee('id="patientFirstName" name="first_name"', false)
            ->assertSee('id="patientMiddleName" name="middle_name"', false)
            ->assertSee('id="patientSuffix" name="suffix"', false)
            ->assertSee('class="consumption-patient-name">ANA REYES</td>', false)
            ->assertSee('ANA REYES', false)
            ->assertSee('BEN SANTOS', false)
            ->assertDontSee('data-visits=', false);

        $this->get('/dashboard/blood-consumption?municipality=Placer')
            ->assertOk()
            ->assertSee('ANA REYES', false)
            ->assertDontSee('BEN SANTOS', false)
            ->assertSee('id="consumption-total">8<', false);
    }

    public function test_patient_history_is_loaded_as_filtered_json_when_requested(): void
    {
        $this->actingAs(User::factory()->create());

        $matchingRecord = ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (Poblacion), Placer, Surigao del Norte',
            'number_of_bags' => 2,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'location' => 'Bayugan City, Agusan del Sur',
            'number_of_bags' => 1,
            'month' => 2,
            'year' => 2026,
            'reporting_period' => 'February 2026',
            'date_submitted' => '2026-02-28',
        ]);

        $this->getJson('/dashboard/blood-consumption?'.http_build_query([
            'details' => 1,
            'patient' => 'ANA REYES',
            'municipality' => 'Placer',
            'component' => 'Packed RBC',
            'month' => 1,
            'year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('patient', 'ANA REYES')
            ->assertJsonPath('visits.0.id', $matchingRecord->id)
            ->assertJsonPath('visits.0.date', 'January 31, 2026')
            ->assertJsonPath('visits.0.address', 'Central (Poblacion), Placer, Surigao del Norte')
            ->assertJsonPath('visits.0.unitsValue', 2)
            ->assertJsonPath('visits.0.editUrl', route('consumption.update', [
                'consumption' => $matchingRecord->id,
                'province' => 'Surigao del Norte',
                'municipality' => 'Placer',
                'component' => 'Packed RBC',
                'month' => 1,
                'year' => 2026,
            ]));
    }

    public function test_patient_history_opens_as_a_full_page_with_edit_and_delete_actions(): void
    {
        $this->actingAs(User::factory()->create());
        ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (Poblacion), Placer, Surigao del Norte',
            'number_of_bags' => 2,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->get('/dashboard/blood-consumption?details=1&details_page=1&patient=ANA%20REYES')
            ->assertOk()
            ->assertSee('Patient Consumption History', false)
            ->assertSee('ANA REYES', false)
            ->assertSee('Packed RBC', false)
            ->assertSee('Central (Poblacion), Placer, Surigao del Norte', false)
            ->assertSee('Edit', false)
            ->assertSee('data-delete-url=', false)
            ->assertDontSee('aria-modal="true"', false);
    }

    public function test_add_and_edit_consumption_forms_open_as_full_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $record = ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (Poblacion), Placer, Surigao del Norte',
            'number_of_bags' => 2,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->get('/dashboard/blood-consumption?add=1')
            ->assertOk()
            ->assertSee('id="consumption-modal" class="page-panel"', false)
            ->assertSee('Add Consumption Record', false)
            ->assertDontSee('id="consumption-modal" class="modal-overlay"', false);

        $this->get('/dashboard/blood-consumption?edit='.$record->id)
            ->assertOk()
            ->assertSee('id="consumption-edit-modal" class="page-panel"', false)
            ->assertSee('value="ANA REYES"', false)
            ->assertSee('value="2026-01-31"', false);
    }

    public function test_patient_history_request_requires_a_patient_name(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/dashboard/blood-consumption?details=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patient');
    }

    public function test_search_returns_only_matching_patient_names_as_json(): void
    {
        $this->actingAs(User::factory()->create());

        ConsumptionRecord::query()->create([
            'patient_name' => 'Ana Reyes',
            'age' => 35,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Placer',
            'location' => 'Placer',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Ben Santos',
            'age' => 28,
            'sex' => 'M',
            'blood_component' => 'Fresh Whole Blood',
            'municipality' => 'Surigao City',
            'location' => 'Surigao City',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->getJson('/dashboard/blood-consumption?search_json=1&q=ana')
            ->assertOk()
            ->assertJsonPath('names', ['ANA REYES'])
            ->assertJsonPath('matchTotal', 1)
            ->assertJsonPath('visibleCount', 1)
            ->assertJsonPath('limitNote', '');
    }

    public function test_patient_search_counts_distinct_normalized_names_and_ignores_null_and_empty_names(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['  DOE ,  JANE   ANN  ', 'doe, jane ann', 'Odd,,Patient', null, ''] as $patientName) {
            ConsumptionRecord::query()->create([
                'patient_name' => $patientName,
                'age' => 35,
                'sex' => 'F',
                'blood_component' => 'Packed RBC',
                'location' => 'Placer',
                'number_of_bags' => 1,
                'month' => 1,
                'year' => 2026,
                'reporting_period' => 'January 2026',
                'date_submitted' => '2026-01-31',
            ]);
        }

        $this->getJson('/dashboard/blood-consumption?search_json=1')
            ->assertOk()
            ->assertJsonPath('names', ['DOE, JANE ANN', 'ODD, , PATIENT'])
            ->assertJsonPath('matchTotal', 2)
            ->assertJsonPath('visibleCount', 2)
            ->assertJsonPath('limitNote', '');
    }

    public function test_consumption_store_rejects_invalid_bag_totals(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from('/dashboard/blood-consumption')
            ->post('/dashboard/blood-consumption', [
                'form' => 'consumption',
                'date_submitted' => '2026-01-31',
                'patient_name' => 'Test Patient',
                'age' => 30,
                'sex' => 'M',
                'blood_component' => 'Packed RBC',
                'province' => 'Surigao del Norte',
                'municipality' => 'Placer',
                'barangay' => 'Central (Poblacion)',
                'units' => 0,
            ])
            ->assertSessionHasErrors('units');

        $this->assertSame(0, ConsumptionRecord::query()->count());
    }

    public function test_consumption_store_accepts_a_typed_municipality(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/dashboard/blood-consumption', [
            'form' => 'consumption',
            'date_submitted' => '2026-01-31',
            'patient_name' => 'Test Patient',
            'age' => 30,
            'sex' => 'M',
            'blood_component' => 'Packed RBC',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'barangay' => 'Barangay 2',
            'units' => 1,
        ])->assertRedirect('/dashboard/blood-consumption?municipality=Bayugan%20City&barangay=Barangay%202');

        $this->assertDatabaseHas('consumption_records', [
            'patient_name' => 'TEST PATIENT',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'location' => 'Barangay 2, Bayugan City, Agusan del Sur',
            'barangay' => 'Barangay 2',
        ]);

        $report = app(ReportService::class)->consumption('Agusan del Sur', '', '1', '2026');
        $province = $report['provinces']->firstWhere('name', 'Agusan del Sur');
        $this->assertIsArray($province);
        $this->assertSame(1, $province['total_units']);
        $this->assertSame('Bayugan City', $province['municipalities'][0]['name']);
        $this->assertSame('TEST PATIENT', $province['municipalities'][0]['records'][0]['patient_name']);
        $this->assertContains('Agusan del Sur', app(ReportService::class)->locations());

        $this->get('/dashboard/reports?view=consumption&consumption_year=2026&consumption_month=1&location=Agusan%20del%20Sur')
            ->assertOk()
            ->assertDontSee('TEST PATIENT', false)
            ->assertSee('Bayugan City', false);

        $this->getJson('/dashboard/reports/consumption-details?'.http_build_query([
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'location' => 'Agusan del Sur',
        ]))
            ->assertOk()
            ->assertJsonPath('records.0.patient_name', 'TEST PATIENT');
    }

    public function test_consumption_store_formats_name_parts_like_blood_collection(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/dashboard/blood-consumption', [
            'form' => 'consumption',
            'date_submitted' => '2026-01-31',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Lourdes',
            'suffix' => 'Jr.',
            'age' => 30,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'units' => 1,
        ])->assertRedirect('/dashboard/blood-consumption?municipality=Bayugan%20City');

        $this->assertDatabaseHas('consumption_records', [
            'patient_name' => 'SANTOS, MARIA LOURDES JR.',
        ]);
    }

    public function test_returning_consumption_search_and_store_reuse_the_latest_patient_details(): void
    {
        $this->actingAs(User::factory()->create());
        ConsumptionRecord::query()->create([
            'patient_name' => 'ANA REYES',
            'age' => 31,
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

        $this->getJson('/dashboard/blood-consumption?returning_patients=1&q=a')
            ->assertOk()
            ->assertJsonPath('patients', ['ANA REYES']);

        $this->post('/dashboard/blood-consumption', [
            'form' => 'consumption',
            'patient_mode' => 'returning',
            'returning_patient_name' => 'ANA REYES',
            'blood_component' => 'Fresh Frozen Plasma',
            'units' => 2,
        ])->assertRedirect('/dashboard/blood-consumption')
            ->assertSessionHas('success', 'Returning patient consumption record saved.');

        $record = ConsumptionRecord::query()->whereDate('date_submitted', $today)->orderByDesc('id')->firstOrFail();
        $this->assertSame('ANA REYES', $record->patient_name);
        $this->assertSame(31, $record->age);
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

    public function test_consumption_record_can_be_edited(): void
    {
        $this->actingAs(User::factory()->create());

        $record = ConsumptionRecord::query()->create([
            'patient_name' => 'OLD NAME',
            'age' => 30,
            'sex' => 'M',
            'blood_component' => 'Packed RBC',
            'blood_type' => 'O+',
            'province' => 'Agusan del Sur',
            'municipality' => 'Bayugan City',
            'barangay' => 'Poblacion',
            'location' => 'Poblacion, Bayugan City, Agusan del Sur',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->patch("/dashboard/blood-consumption/{$record->id}", [
            'consumption_id' => $record->id,
            'patient_name' => 'SANTOS, MARIA LOURDES JR.',
            'date_submitted' => '2026-02-15',
            'age' => 31,
            'sex' => 'F',
            'blood_component' => 'Fresh Frozen Plasma',
            'units' => 3,
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
        ])->assertRedirect('/dashboard/blood-consumption?municipality=Placer&barangay=Central%20%28Poblacion%29');

        $this->assertDatabaseHas('consumption_records', [
            'id' => $record->id,
            'patient_name' => 'SANTOS, MARIA LOURDES JR.',
            'age' => 31,
            'sex' => 'F',
            'blood_component' => 'Fresh Frozen Plasma',
            'number_of_bags' => 3,
            'province' => 'Surigao del Norte',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (poblacion), Placer, Surigao del Norte',
            'month' => 2,
            'year' => 2026,
            'reporting_period' => 'February 2026',
            'date_submitted' => '2026-02-15 00:00:00',
        ]);
    }

    public function test_all_consumption_records_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());

        ConsumptionRecord::query()->create([
            'patient_name' => 'First Patient',
            'location' => 'Placer, Surigao del Norte',
            'blood_type' => 'O+',
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'number_of_bags' => 1,
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Second Patient',
            'location' => 'Bayugan City, Agusan del Sur',
            'blood_type' => 'A+',
            'month' => 2,
            'year' => 2026,
            'reporting_period' => 'February 2026',
            'number_of_bags' => 2,
            'date_submitted' => '2026-02-28',
        ]);

        $response = $this->get('/dashboard/blood-consumption')
            ->assertSee('Delete All', false)
            ->assertSee(route('consumption.destroy-all'), false);
        $tableHeader = substr(
            $response->getContent(),
            strpos($response->getContent(), '<table class="records-table" id="consumption-table">')
        );
        $tableHeader = substr($tableHeader, 0, strpos($tableHeader, '</thead>'));
        $this->assertStringContainsString('patient-name-heading', $tableHeader);
        $this->assertStringContainsString('Patient Name', $tableHeader);
        $this->assertStringContainsString('Delete All', $tableHeader);

        $this->delete('/dashboard/blood-consumption')
            ->assertRedirect('/dashboard/blood-consumption')
            ->assertSessionHas('success', '2 consumption records were deleted.');

        $this->assertDatabaseCount('consumption_records', 0);
    }

    public function test_province_filter_scopes_municipalities_and_clears_dependent_filters_on_tab_switch(): void
    {
        $this->actingAs(User::factory()->create());

        ConsumptionRecord::query()->create([
            'patient_name' => 'Ana Cruz',
            'age' => 30,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Placer',
            'barangay' => 'Central (Poblacion)',
            'location' => 'Central (Poblacion), Placer',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Ben Santos',
            'age' => 30,
            'sex' => 'M',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Bayugan City',
            'barangay' => 'Poblacion',
            'location' => 'Bayugan City',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);
        ConsumptionRecord::query()->create([
            'patient_name' => 'Cara Brown',
            'age' => 30,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'municipality' => 'Baguio City',
            'location' => 'Baguio City',
            'number_of_bags' => 1,
            'month' => 1,
            'year' => 2026,
            'reporting_period' => 'January 2026',
            'date_submitted' => '2026-01-31',
        ]);

        $this->get('/dashboard/blood-consumption?province=Agusan%20del%20Sur')
            ->assertOk()
            ->assertSee('BEN SANTOS', false)
            ->assertSee('id="consumption-count">1<', false)
            ->assertDontSee('ANA CRUZ', false);

        $allRecords = $this->get('/dashboard/blood-consumption')
            ->assertOk()
            ->assertSee('ANA CRUZ', false)
            ->assertSee('BEN SANTOS', false)
            ->assertSee('CARA BROWN', false)
            ->assertSee('id="consumption-count">3<', false)
            ->assertSee('class="province-tab is-active"', false)
            ->assertSee('href="http://localhost/dashboard/blood-consumption"', false);

        $allRecordsHtml = $allRecords->getContent();
        $provinceTabsStart = strpos($allRecordsHtml, 'class="province-tabs"');
        $provinceTabsEnd = strpos($allRecordsHtml, '</div>', $provinceTabsStart);
        $provinceTabsHtml = substr($allRecordsHtml, $provinceTabsStart, $provinceTabsEnd - $provinceTabsStart);
        $this->assertLessThan(
            strpos($provinceTabsHtml, '>Agusan del Sur</a>'),
            strpos($provinceTabsHtml, '>Agusan del Norte</a>')
        );
        $this->assertLessThan(
            strpos($provinceTabsHtml, '>Dinagat Islands</a>'),
            strpos($provinceTabsHtml, '>Agusan del Sur</a>')
        );
        $this->assertLessThan(
            strpos($provinceTabsHtml, '>Surigao del Norte</a>'),
            strpos($provinceTabsHtml, '>Dinagat Islands</a>')
        );
        $this->assertLessThan(
            strpos($provinceTabsHtml, '>Surigao del Sur</a>'),
            strpos($provinceTabsHtml, '>Surigao del Norte</a>')
        );
        $this->assertStringNotContainsString('>Outside</a>', $provinceTabsHtml);

        $this->get('/dashboard/blood-consumption?province=Agusan%20del%20Sur&municipality=Bayugan%20City')
            ->assertOk()
            ->assertSee('BEN SANTOS', false)
            ->assertDontSee('ANA CRUZ', false)
            ->assertDontSee('id="filter-municipality"', false)
            ->assertDontSee('id="filter-barangay"', false);

        $this->get('/dashboard/blood-consumption?province=Surigao%20del%20Norte&municipality=Placer&barangay=Central%20(Poblacion)')
            ->assertOk()
            ->assertSee('Central (Poblacion)', false)
            ->assertSee('province=Dinagat%20Islands', false)
            ->assertDontSee('province=Dinagat%20Islands&amp;municipality=', false)
            ->assertSee('href="http://localhost/dashboard/blood-consumption"', false)
            ->assertSee('ANA CRUZ', false)
            ->assertDontSee('BEN SANTOS', false);
    }
}
