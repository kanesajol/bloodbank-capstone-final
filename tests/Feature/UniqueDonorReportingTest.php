<?php

namespace Tests\Feature;

use App\Models\ConsumptionRecord;
use App\Models\Donor;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UniqueDonorReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_age_sixteen_and_seventeen_donors_are_included_in_age_reports(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([16, 17] as $age) {
            $donor = Donor::query()->create([
                'full_name' => 'Donor Age '.$age,
                'blood_type' => 'O+',
                'age' => $age,
                'gender' => 'Female',
                'contact' => '091800000'.$age,
                'barangay' => 'Washington',
                'municipality' => 'Surigao City',
                'province' => 'Surigao del Norte',
                'registered_date' => '2026-06-10',
            ]);

            $donor->donations()->create([
                'donated_on' => '2026-06-28',
                'blood_type' => 'O+',
                'location' => 'Surigao City',
                'bags' => 1,
            ]);
        }

        $summary = app(ReportService::class)->demographics('2026', '6', 'Surigao del Norte');

        $this->assertSame('16–25', $summary['age']['groups'][0]['group']);
        $this->assertSame(2, $summary['age']['groups'][0]['count']);

        $this->getJson('/dashboard/reports/demographic-details?'.http_build_query([
            'type' => 'age',
            'group' => '16–25',
            'month' => '6',
            'year' => '2026',
            'location' => 'Surigao del Norte',
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'donors')
            ->assertJsonFragment(['age' => 16])
            ->assertJsonFragment(['age' => 17]);
    }

    public function test_later_donation_stays_on_one_profile_and_reports_use_permanent_address(): void
    {
        $this->actingAs(User::factory()->create());

        $profile = [
            'full_name' => 'Maria Santos',
            'blood_type' => 'O+',
            'age' => 34,
            'gender' => 'Female',
            'contact' => '09181234567',
            'barangay' => 'Tubajon',
            'municipality' => 'Dinagat Islands',
            'province' => '',
            'initial_donation_date' => '2026-01-15',
            'donation_location' => 'Tubajon Health Center',
        ];

        $this->post('/dashboard/blood-collection/donors', $profile)->assertRedirect();

        $this->post('/dashboard/donors', [
            ...$profile,
            'full_name' => 'maria santos',
            'barangay' => 'Washington',
            'municipality' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'initial_donation_date' => '2026-06-20',
            'donation_location' => 'Caraga Bloodbank',
        ])->assertRedirect('/dashboard');

        $this->assertSame(1, Donor::query()->count());

        $donor = Donor::query()->with('donations')->first();
        $this->assertNotNull($donor);
        $this->assertSame('Tubajon', $donor->barangay);
        $this->assertSame('Dinagat Islands', $donor->municipality);
        $this->assertNull($donor->province);
        $this->assertSame(
            ['Tubajon Health Center', 'Caraga Bloodbank'],
            $donor->donations->sortBy('donated_on')->pluck('location')->all()
        );

        Donor::recordProfileOrDonation([
            'full_name' => 'Pedro Reyes',
            'blood_type' => 'A+',
            'age' => 41,
            'gender' => 'Male',
            'contact' => '09170000000',
            'barangay' => 'San Jose',
            'municipality' => 'Dinagat Islands',
            'province' => null,
            'initial_donation_date' => '2026-03-02',
            'donation_location' => 'San Jose RHU',
        ]);

        $reports = app(ReportService::class);
        $home = $reports->yearly(2026, 'Tubajon');

        $this->assertSame(1, $home['totalDonors']);
        $this->assertSame(2, $home['totalDonations']);
        $this->assertSame(1, $home['repeatDonorsCount']);
        $this->assertSame('Tubajon, Dinagat Islands', $home['byLocation'][0]['name']);
        $this->assertSame(1, $home['byLocation'][0]['donorCount']);
        $this->assertSame(2, $home['byLocation'][0]['donationCount']);

        $this->assertSame(0, $reports->yearly(2026, 'Caraga Bloodbank')['totalDonors']);

        $all = $reports->yearly(2026);
        $this->assertSame(2, $all['totalDonors']);
        $this->assertSame(3, $all['totalDonations']);

        $donor->donations()->create([
            'donated_on' => '2026-06-28',
            'blood_type' => 'O+',
            'location' => 'Tubajon Health Center',
            'bags' => 1,
        ]);

        $monthly = $reports->monthly(6, 2026, 'Tubajon');
        $this->assertSame(1, $monthly['uniqueDonors']);
        $this->assertCount(1, $monthly['donations']);
        $this->assertSame(2, $monthly['donations'][0]['donation_count']);
        $this->assertSame(3, $monthly['donations'][0]['lifetime_count']);
        $this->assertSame('Tubajon, Dinagat Islands', $monthly['donations'][0]['location']);
        $this->assertSame(
            ['Tubajon Health Center', 'Caraga Bloodbank', 'Tubajon Health Center'],
            collect($monthly['donations'][0]['history'])->pluck('location')->all()
        );

        $this->get('/dashboard/reports?tab=yearly&year=2026&location=Tubajon')
            ->assertOk()
            ->assertSee('Tubajon, Dinagat Islands', false)
            ->assertDontSee('Caraga Bloodbank', false);

        $this->get('/dashboard/reports?'.http_build_query([
            'tab' => 'age',
            'location' => 'Tubajon',
            'group' => '26–35',
        ]))->assertOk()->assertDontSee('Maria Santos', false);
        $this->get('/dashboard/reports?tab=gender&gender_year=2026&location=Dinagat%20Islands')
            ->assertOk()
            ->assertDontSee('Maria Santos', false);

        $demographics = $this->get('/dashboard/reports?view=demographics&month=6&year=2026');
        $demographics->assertOk()
            ->assertDontSee('Maria Santos', false)
            ->assertDontSee('Pedro Reyes', false)
            ->assertSee('26–35', false)
            ->assertSee('Gender Comparison', false)
            ->assertDontSee('id="demoMonth"', false)
            ->assertDontSee('id="demoYear"', false)
            ->assertSee('id="toolbarMonth" value="6"', false)
            ->assertSee('id="toolbarYear" value="2026"', false);

        $summary = app(ReportService::class)->demographics('2026', '6', 'Dinagat Islands');
        $this->assertSame(1, $summary['age']['groups'][1]['count']);
        $this->assertSame(2, $summary['age']['groups'][1]['totalDonations']);
        $this->assertSame(1, $summary['gender']['rows'][0]['count']);
        $this->assertSame(2, $summary['gender']['rows'][0]['bags']);
        $this->assertSame('O+', $summary['gender']['rows'][0]['topBloodType']);

        $this->getJson('/dashboard/reports/demographic-details?'.http_build_query([
            'type' => 'age',
            'group' => '26–35',
            'month' => '6',
            'year' => '2026',
            'location' => 'Dinagat Islands',
        ]))
            ->assertOk()
            ->assertJsonPath('donors.0.name', 'Maria Santos')
            ->assertJsonPath('donors.0.age', 34);

        $this->getJson('/dashboard/reports/demographic-details?'.http_build_query([
            'type' => 'gender',
            'group' => 'Female',
            'month' => '6',
            'year' => '2026',
            'location' => 'Dinagat Islands',
        ]))
            ->assertOk()
            ->assertJsonPath('donors.0.name', 'Maria Santos')
            ->assertJsonPath('donors.0.last_donation_display', 'June 28, 2026');

        $this->getJson('/dashboard/reports/demographic-details?'.http_build_query([
            'type' => 'age',
            'group' => 'Female',
            'month' => '6',
            'year' => '2026',
        ]))->assertUnprocessable()->assertJsonValidationErrors('group');

        $monthlyPage = $this->get('/dashboard/reports?tab=monthly&month=6&year=2026&location=Tubajon');
        $monthlyPage->assertOk()
            ->assertSee('Maria Santos', false)
            ->assertSee('Tubajon, Dinagat Islands', false)
            ->assertDontSee('Monthly Donor Report', false)
            ->assertDontSee('Donor Records List', false);
    }

    public function test_demographics_report_separates_collection_and_consumption_age_gender_totals(): void
    {
        $this->actingAs(User::factory()->create());

        Donor::query()->create([
            'full_name' => 'Collection Donor',
            'blood_type' => 'O+',
            'age' => 24,
            'gender' => 'Female',
            'contact' => '09181112222',
            'barangay' => 'Washington',
            'municipality' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'registered_date' => '2026-06-10',
        ])->donations()->create([
            'donated_on' => '2026-06-28',
            'blood_type' => 'O+',
            'location' => 'Surigao City',
            'bags' => 1,
        ]);

        foreach ([
            [
                'patient_name' => 'Ava Santos',
                'age' => 24,
                'sex' => 'F',
                'number_of_bags' => 2,
                'month' => 6,
                'year' => 2026,
                'province' => 'Surigao del Norte',
            ],
            [
                'patient_name' => 'Miguel Reyes',
                'age' => 34,
                'sex' => 'M',
                'number_of_bags' => 3,
                'month' => 6,
                'year' => 2026,
                'province' => 'Surigao del Norte',
            ],
            [
                'patient_name' => 'Luna Garcia',
                'age' => 42,
                'sex' => 'F',
                'number_of_bags' => 4,
                'month' => 7,
                'year' => 2026,
                'province' => 'Surigao del Norte',
            ],
            [
                'patient_name' => 'Agusan Patient',
                'age' => 56,
                'sex' => 'M',
                'number_of_bags' => 1,
                'month' => 6,
                'year' => 2026,
                'province' => 'Agusan del Norte',
            ],
            [
                'patient_name' => 'Old Patient',
                'age' => 36,
                'sex' => 'F',
                'number_of_bags' => 5,
                'month' => 6,
                'year' => 2025,
                'province' => 'Surigao del Norte',
            ],
            [
                'patient_name' => 'ava santos',
                'age' => 24,
                'sex' => 'f',
                'number_of_bags' => 3,
                'month' => 6,
                'year' => 2026,
                'province' => 'Surigao del Norte',
            ],
        ] as $record) {
            ConsumptionRecord::query()->create([
                ...$record,
                'blood_component' => 'Packed RBC',
                'location' => $record['province'],
                'municipality' => 'Surigao City',
                'barangay' => 'Washington',
                'blood_type' => 'O+',
                'reporting_period' => ($record['month'] === 6 ? 'June ' : 'July ').$record['year'],
                'date_submitted' => sprintf('%04d-%02d-28', $record['year'], $record['month']),
            ]);
        }

        $summary = app(ReportService::class)->consumptionDemographics('6', '2026', 'Surigao del Norte');

        $this->assertSame(2, $summary['age']['totalPatients']);
        $this->assertSame(['group' => '16–25', 'count' => 1, 'bags' => 5], $summary['age']['groups'][0]);
        $this->assertSame(['group' => '26–35', 'count' => 1, 'bags' => 3], $summary['age']['groups'][1]);
        $this->assertSame(['gender' => 'Female', 'count' => 1, 'bags' => 5], $summary['gender']['rows'][0]);
        $this->assertSame(['gender' => 'Male', 'count' => 1, 'bags' => 3], $summary['gender']['rows'][1]);

        $this->getJson('/dashboard/reports/consumption-demographic-details?'.http_build_query([
            'type' => 'age',
            'group' => '16–25',
            'month' => '6',
            'year' => '2026',
            'location' => 'Surigao del Norte',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'patients')
            ->assertJsonPath('patients.0.patient_name', 'Ava Santos')
            ->assertJsonPath('patients.0.bags', 5);

        $this->getJson('/dashboard/reports/consumption-demographic-details?'.http_build_query([
            'type' => 'gender',
            'group' => 'Female',
            'month' => '6',
            'year' => '2026',
            'location' => 'Surigao del Norte',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'patients')
            ->assertJsonPath('patients.0.patient_name', 'Ava Santos')
            ->assertJsonPath('patients.0.bags', 5);

        $this->getJson('/dashboard/reports/consumption-demographic-details?'.http_build_query([
            'type' => 'gender',
            'group' => '16–25',
        ]))->assertUnprocessable()->assertJsonValidationErrors('group');

        $this->get('/dashboard/reports?view=periodic')
            ->assertOk()
            ->assertDontSee('data-report-panel="demographics" data-loaded="1"', false);

        $this->get('/dashboard/reports?'.http_build_query([
            'view' => 'demographics',
            'month' => '6',
            'year' => '2026',
            'location' => 'Surigao del Norte',
        ]))
            ->assertOk()
            ->assertSee('Blood Collection — Age Group Distribution', false)
            ->assertSee('Blood Collection — Gender Comparison', false)
            ->assertSee('Blood Consumption — Age Group Distribution', false)
            ->assertSee('Blood Consumption — Gender Comparison', false)
            ->assertSee('data-consumption-demographic-type="age"', false)
            ->assertSee('data-consumption-demographic-type="gender"', false)
            ->assertSee('2 Patients', false);
    }

    public function test_print_all_renders_periodic_demographic_and_consumption_reports_together(): void
    {
        $this->actingAs(User::factory()->create());

        $donor = Donor::query()->create([
            'full_name' => 'Print Report Donor',
            'blood_type' => 'O+',
            'age' => 35,
            'gender' => 'Female',
            'contact' => '09181112222',
            'barangay' => 'Washington',
            'municipality' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'registered_date' => '2026-06-10',
        ]);
        $donor->donations()->create([
            'donated_on' => '2026-06-28',
            'blood_type' => 'O+',
            'location' => 'Surigao City',
            'bags' => 1,
        ]);

        ConsumptionRecord::query()->create([
            'patient_name' => 'Print Consumption Patient',
            'age' => 40,
            'sex' => 'F',
            'blood_component' => 'Packed RBC',
            'location' => 'Washington, Surigao City, Surigao del Norte',
            'province' => 'Surigao del Norte',
            'municipality' => 'Surigao City',
            'barangay' => 'Washington',
            'blood_type' => 'O+',
            'number_of_bags' => 2,
            'month' => 6,
            'year' => 2026,
            'reporting_period' => 'June 2026',
            'date_submitted' => '2026-06-28',
        ]);

        $this->get('/dashboard/reports?view=periodic&period=monthly&year=2026&print_all=1')
            ->assertOk()
            ->assertSee('print-all-reports', false)
            ->assertSee('Periodic Collections, Age &amp; Gender Demographics, and Blood Consumption', false)
            ->assertSee('Monthly collections 2026', false)
            ->assertSee('Periodic Collections', false)
            ->assertSee('Age &amp; Gender Demographics', false)
            ->assertSee('Blood Consumption', false)
            ->assertSee('Blood Consumption — Age Group Distribution', false)
            ->assertSee('Blood Consumption — Gender Comparison', false)
            ->assertSee('Total Donors', false)
            ->assertSee('Total Collections / Bags', false)
            ->assertSee('April to June', false)
            ->assertSee('January to March', false)
            ->assertSee('June', false)
            ->assertSee('<span>January</span>', false)
            ->assertDontSee('<td>January</td>', false)
            ->assertSee('<span>Total</span>', false)
            ->assertSee('Yearly', false)
            ->assertSee('Total Consumed Units:', false)
            ->assertDontSee('Print Consumption Patient', false)
            ->assertDontSee('print-consumption-records', false)
            ->assertSee('All Months 2026', false)
            ->assertSee('print-book', false)
            ->assertSee('1 / 1', false)
            ->assertDontSee('2 / 3', false)
            ->assertDontSee('3 / 3', false)
            ->assertSee('>Monthly<', false)
            ->assertSee('>Quarterly<', false)
            ->assertSee('>Yearly<', false);

        $this->get('/dashboard/reports?view=periodic&period=monthly&year=2024&print_all=1')
            ->assertOk()
            ->assertSee('No collections recorded for this period', false)
            ->assertDontSee('<td>January</td>', false)
            ->assertDontSee('<td>2024</td>', false);
    }
}
