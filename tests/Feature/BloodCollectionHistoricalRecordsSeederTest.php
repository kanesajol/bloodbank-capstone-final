<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Database\Seeders\BloodCollectionHistoricalRecordsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BloodCollectionHistoricalRecordsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    public function test_historical_records_are_imported_once_without_overwriting_existing_history(): void
    {
        $existingDonor = Donor::query()->create([
            'full_name' => 'SALUPAN, SALVADOR O.',
            'blood_type' => 'O+',
            'age' => 99,
            'gender' => 'Male',
            'barangay' => '',
            'municipality' => '',
            'contact' => 'existing-contact',
            'registered_date' => '2025-01-01',
        ]);
        $existingDonation = $existingDonor->donations()->create([
            'donated_on' => '2025-01-01',
            'blood_type' => 'O+',
            'location' => 'Existing location',
            'bags' => 1,
            'mbd_date' => 'Existing MBD',
            'serial_number' => '20240041592',
            'status' => 'passed',
            'remarks' => 'Existing note',
        ]);

        $this->seed(BloodCollectionHistoricalRecordsSeeder::class);
        $this->seed(BloodCollectionHistoricalRecordsSeeder::class);

        $this->assertDatabaseCount('donations', 21);
        $this->assertDatabaseCount('donors', 21);
        $this->assertDatabaseHas('donors', [
            'id' => $existingDonor->id,
            'full_name' => 'SALUPAN, SALVADOR O.',
            'age' => 99,
            'contact' => 'existing-contact',
        ]);
        $this->assertDatabaseHas('donations', [
            'id' => $existingDonation->id,
            'mbd_date' => 'Existing MBD',
            'serial_number' => '20240041592',
            'remarks' => 'Existing note',
        ]);
        $this->assertDatabaseHas('donations', [
            'serial_number' => '202400041596',
            'blood_type' => 'A+',
            'reserved_for_px' => 'MAPALO, MARTINITO',
            'remarks' => 'MAPALO, MARTINITO',
        ]);
        $deferredDonation = Donation::query()
            ->where('status', 'deferred')
            ->where('reason_for_deferral', 'UNDERWEIGHT')
            ->firstOrFail();
        $this->assertSame('2025-01-01', $deferredDonation->donated_on->toDateString());
        $this->assertSame('UNDERWEIGHT', $deferredDonation->remarks);
        $this->assertSame('', $deferredDonation->blood_type);
        $this->assertNull($deferredDonation->serial_number);
        $jualo = Donor::query()->where('full_name', 'JUALO, SAMUEL CANOY')->firstOrFail();
        $juaco = Donor::query()->where('full_name', 'JUACO, SAMUEL CANOY')->firstOrFail();
        $this->assertSame(33, $jualo->age);
        $this->assertSame('1931-08-18', $jualo->birthdate->toDateString());
        $this->assertSame(33, $juaco->age);
        $this->assertSame('1991-08-18', $juaco->birthdate->toDateString());

        $this->actingAs(User::factory()->create())
            ->get("/dashboard/blood-collection?mode=name&q=JUALO&donor={$jualo->id}")
            ->assertSee('JUALO, SAMUEL CANOY', false)
            ->assertSee('Deferred', false)
            ->assertSee('LACK OF SLEEP', false);
    }
}
