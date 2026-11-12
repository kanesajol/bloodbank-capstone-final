<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BloodConsumptionController;
use App\Http\Controllers\Controller;
use App\Models\ConsumptionRecord;
use App\Support\BloodBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsumptionController extends Controller
{
    /** @var list<string> */
    private const COMPONENTS = [
        'Cryoprecipitate',
        'Fresh Frozen Plasma',
        'Fresh Whole Blood',
        'Packed RBC',
        'Platelet Apheresis',
        'Platelet Concentrate',
        'Washed RBC',
    ];

    public function meta(): JsonResponse
    {
        [$sdn, $outside] = BloodConsumptionController::placeGroups();
        $currentYear = (int) now()->year;
        $choices = BloodConsumptionController::formLocationChoices();

        return response()->json([
            'components' => self::COMPONENTS,
            'provinces' => BloodConsumptionController::PROVINCES,
            'municipalitiesByProvince' => $choices['municipalitiesByProvince'],
            'places' => $choices['places'],
            'sdnMunicipalities' => array_keys($sdn),
            'outsidePlaces' => array_keys($outside),
            'months' => BloodBank::MONTHS,
            'years' => [$currentYear - 1, $currentYear, $currentYear + 1],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $places = BloodConsumptionController::places();
        $province = (string) $request->query('province', '');
        if (! in_array($province, BloodConsumptionController::PROVINCES, true)) {
            $province = '';
        }

        $municipality = $this->canonicalPlace((string) $request->query('municipality', ''), $places);
        if ($province === '' && isset(BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality])) {
            $province = BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality];
        }
        if ($municipality !== '' && $province !== ''
            && (BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality] ?? '') !== $province) {
            $municipality = '';
        }

        $barangays = $municipality !== '' ? ($places[$municipality] ?? []) : [];
        $barangay = $this->canonicalBarangay((string) $request->query('barangay', ''), $barangays);
        $component = (string) $request->query('component', '');
        if (! in_array($component, self::COMPONENTS, true)) {
            $component = '';
        }

        $monthNumber = (int) $request->query('month', 0);
        if ($monthNumber < 1 || $monthNumber > 12) {
            $monthNumber = 0;
        }

        $yearNumber = (int) $request->query('year', 0);
        if ($yearNumber < 2000 || $yearNumber > 2100) {
            $yearNumber = 0;
        }

        $search = trim((string) $request->query('q', ''));

        $filtered = ConsumptionRecord::query()
            ->whereNotNull('patient_name')
            ->where('patient_name', '!=', '')
            ->when($province !== '' && $municipality === '', function ($query) use ($province) {
                $municipalities = array_keys(array_filter(
                    BloodBank::MUNICIPALITY_PROVINCE_MAP,
                    fn (string $provinceName) => $provinceName === $province
                ));

                $query->where(function ($placeQuery) use ($municipalities, $province) {
                    $placeQuery->whereIn('municipality', $municipalities)
                        ->orWhere(function ($legacy) use ($municipalities) {
                            $legacy->whereNull('municipality')
                                ->whereIn('location', $municipalities);
                        })
                        ->orWhere('province', $province);
                });
            })
            ->when($municipality !== '', function ($query) use ($municipality) {
                $query->where(function ($inner) use ($municipality) {
                    $inner->where('municipality', $municipality)
                        ->orWhere(function ($legacy) use ($municipality) {
                            $legacy->whereNull('municipality')
                                ->where('location', $municipality);
                        });
                });
            })
            ->when($barangay !== '', function ($query) use ($barangay) {
                $query->where(function ($inner) use ($barangay) {
                    $inner->where('barangay', $barangay)
                        ->orWhere(function ($legacy) use ($barangay) {
                            $legacy->whereNull('barangay')
                                ->where('location', 'like', '%'.$barangay.'%');
                        });
                });
            })
            ->when($component !== '', fn ($query) => $query->where('blood_component', $component))
            ->when($monthNumber > 0, fn ($query) => $query->where('month', $monthNumber))
            ->when($yearNumber > 0, fn ($query) => $query->where('year', $yearNumber));

        $totalUnits = (int) (clone $filtered)->sum('number_of_bags');

        $list = (clone $filtered);
        foreach ($this->searchWords($search) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $list->whereRaw('LOWER(patient_name) like ?', [$like]);
        }

        if ($request->boolean('details')) {
            $validated = $request->validate([
                'patient' => ['required', 'string', 'max:255'],
            ]);

            $patientName = BloodConsumptionController::displayName($validated['patient']);
            $patientIds = (clone $list)
                ->get(['id', 'patient_name'])
                ->filter(fn (ConsumptionRecord $record) => BloodConsumptionController::displayName((string) $record->patient_name) === $patientName)
                ->pluck('id');
            abort_if($patientIds->isEmpty(), 404);

            $visits = (clone $list)
                ->whereIn('id', $patientIds)
                ->orderByDesc('date_submitted')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ConsumptionRecord $record) => [
                    'id' => $record->id,
                    'patient_name' => BloodConsumptionController::displayName((string) $record->patient_name),
                    'age' => $record->age,
                    'birthdate' => $record->birthdate?->toDateString(),
                    'sex' => $record->sex,
                    'blood_component' => $record->blood_component,
                    'units' => (int) $record->number_of_bags,
                    'municipality' => $record->municipality,
                    'province' => $record->province,
                    'barangay' => $record->barangay,
                    'location' => $record->location,
                    'date_submitted' => $record->date_submitted?->toDateString(),
                ])
                ->values();

            return response()->json([
                'patient' => $patientName,
                'visits' => $visits,
            ]);
        }

        $patients = (clone $list)
            ->select('patient_name')
            ->orderBy('patient_name')
            ->pluck('patient_name')
            ->map(fn (string $name) => BloodConsumptionController::displayName($name))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $matchTotal = $patients->count();

        $records = $list
            ->orderByDesc('date_submitted')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (ConsumptionRecord $record) => [
                'id' => $record->id,
                'patient_name' => BloodConsumptionController::displayName((string) $record->patient_name),
                'age' => $record->age,
                'birthdate' => $record->birthdate?->toDateString(),
                'sex' => $record->sex,
                'blood_component' => $record->blood_component,
                'units' => (int) $record->number_of_bags,
                'municipality' => $record->municipality,
                'barangay' => $record->barangay,
                'location' => $record->location,
                'date_submitted' => $record->date_submitted?->toDateString(),
            ])
            ->values();

        $componentSums = (clone $filtered)
            ->whereIn('blood_component', self::COMPONENTS)
            ->select('blood_component')
            ->selectRaw('SUM(number_of_bags) as total')
            ->groupBy('blood_component')
            ->pluck('total', 'blood_component');

        $totalsByComponent = [];
        foreach (self::COMPONENTS as $name) {
            $totalsByComponent[] = [
                'component' => $name,
                'total' => (int) ($componentSums[$name] ?? 0),
            ];
        }

        return response()->json([
            'totalUnits' => $totalUnits,
            'totalsByComponent' => $totalsByComponent,
            'patients' => $patients,
            'matchTotal' => $matchTotal,
            'recordCount' => ConsumptionRecord::query()->count(),
            'records' => $records,
            'filters' => [
                'province' => $province,
                'municipality' => $municipality,
                'barangay' => $barangay,
                'component' => $component,
                'month' => $monthNumber > 0 ? $monthNumber : null,
                'year' => $yearNumber > 0 ? $yearNumber : null,
                'q' => $search,
            ],
            'barangays' => $barangays,
        ]);
    }

    public function returningPatients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'patients' => BloodConsumptionController::returningPatientNames($validated['q']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['patient_mode' => $request->input('patient_mode', 'new')]);
        $isReturningPatient = $request->input('patient_mode') === 'returning';
        $province = (string) $request->input('province', '');
        $municipalities = array_keys(array_filter(
            BloodBank::MUNICIPALITY_PROVINCE_MAP,
            fn (string $mappedProvince): bool => $mappedProvince === $province
        ));
        $validated = $request->validate([
            'patient_mode' => ['required', Rule::in(['new', 'returning'])],
            'returning_patient_name' => [Rule::requiredIf($isReturningPatient), 'nullable', 'string', 'max:255'],
            'date_submitted' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'date'],
            'patient_name' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'string', 'max:255'],
            'age' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'integer', 'min:0', 'max:120'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => [Rule::requiredIf(! $isReturningPatient), 'nullable', Rule::in(['M', 'F'])],
            'blood_component' => ['required', Rule::in(self::COMPONENTS)],
            'units' => ['required', 'integer', 'min:1'],
            'province' => [Rule::requiredIf(! $isReturningPatient), 'nullable', Rule::in(BloodConsumptionController::PROVINCES)],
            'municipality' => [Rule::requiredIf(! $isReturningPatient), 'nullable', Rule::in($municipalities)],
            'barangay' => ['nullable', 'string', 'max:255'],
        ]);

        if ($isReturningPatient) {
            $record = BloodConsumptionController::createReturningConsumption(
                $validated['returning_patient_name'],
                $validated['blood_component'],
                (int) $validated['units'],
            );

            if ($record === null) {
                throw ValidationException::withMessages([
                    'returning_patient_name' => ['Select a patient with an existing consumption record.'],
                ]);
            }

            return response()->json([
                'message' => 'Returning patient consumption record saved.',
                'record' => [
                    'id' => $record->id,
                    'patient_name' => $record->patient_name,
                ],
            ], 201);
        }

        $places = $this->placesWithStoredBarangays();
        $municipality = $validated['municipality'];
        $barangayList = $places[$municipality];
        $barangay = $this->canonicalBarangay((string) ($validated['barangay'] ?? ''), $barangayList);

        if (($barangayList !== [] && $barangay === '')
            || (trim((string) ($validated['barangay'] ?? '')) !== '' && $barangay === '')) {
            return response()->json([
                'message' => 'Select a valid barangay for this municipality.',
                'errors' => ['barangay' => ['Select a valid barangay for this municipality.']],
            ], 422);
        }

        $submitted = Carbon::parse($validated['date_submitted']);
        $province = $validated['province'];
        $record = new ConsumptionRecord;
        $record->forceFill([
            'patient_name' => BloodConsumptionController::displayName($validated['patient_name']),
            'age' => (int) $validated['age'],
            'birthdate' => $validated['birthdate'] ?? null,
            'sex' => $validated['sex'],
            'blood_component' => $validated['blood_component'],
            'municipality' => $municipality,
            'province' => $province,
            'barangay' => $barangay !== '' ? $barangay : null,
            'location' => BloodBank::formatAssignedAddress($barangay, $municipality, $province),
            'blood_type' => null,
            'number_of_bags' => (int) $validated['units'],
            'month' => (int) $submitted->month,
            'year' => (int) $submitted->year,
            'reporting_period' => BloodBank::MONTHS[$submitted->month - 1].' '.$submitted->year,
            'date_submitted' => $validated['date_submitted'],
        ])->save();

        return response()->json([
            'message' => 'Consumption record saved.',
            'record' => [
                'id' => $record->id,
                'patient_name' => $record->patient_name,
            ],
        ], 201);
    }

    public function update(Request $request, ConsumptionRecord $consumption): JsonResponse
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'date_submitted' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['M', 'F'])],
            'blood_component' => ['required', Rule::in(self::COMPONENTS)],
            'units' => ['required', 'integer', 'min:1'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
        ]);

        $places = $this->placesWithStoredBarangays();
        $municipality = $this->canonicalPlace($validated['municipality'], $places);
        if ($municipality === '') {
            $municipality = trim($validated['municipality']);
        }

        $barangay = trim((string) ($validated['barangay'] ?? ''));
        $barangay = $this->canonicalBarangay($barangay, $places[$municipality] ?? []) ?: $barangay;
        $submitted = Carbon::parse($validated['date_submitted']);
        $province = trim($validated['province']);

        $consumption->forceFill([
            'patient_name' => BloodConsumptionController::displayName($validated['patient_name']),
            'date_submitted' => $validated['date_submitted'],
            'age' => (int) $validated['age'],
            'birthdate' => $validated['birthdate'] ?? $consumption->birthdate,
            'sex' => $validated['sex'],
            'blood_component' => $validated['blood_component'],
            'number_of_bags' => (int) $validated['units'],
            'municipality' => $municipality,
            'province' => $province,
            'barangay' => $barangay !== '' ? $barangay : null,
            'location' => BloodBank::cleanAddressString(implode(', ', array_filter([
                $barangay,
                $municipality,
                $province,
            ]))),
            'month' => (int) $submitted->month,
            'year' => (int) $submitted->year,
            'reporting_period' => BloodBank::MONTHS[$submitted->month - 1].' '.$submitted->year,
        ])->save();

        return response()->json([
            'message' => 'Consumption record updated.',
            'record' => [
                'id' => $consumption->id,
                'patient_name' => $consumption->patient_name,
            ],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function placesWithStoredBarangays(): array
    {
        return BloodConsumptionController::formLocationChoices()['places'];
    }

    public function destroy(ConsumptionRecord $consumption): JsonResponse
    {
        $label = $consumption->patient_name ?: $consumption->location;
        $consumption->delete();

        return response()->json([
            'message' => "Consumption record for {$label} was deleted.",
        ]);
    }

    public function destroyAll(): JsonResponse
    {
        $count = ConsumptionRecord::query()->count();
        ConsumptionRecord::query()->delete();

        return response()->json([
            'deletedCount' => $count,
            'message' => $count === 1
                ? '1 consumption record was deleted.'
                : "{$count} consumption records were deleted.",
        ]);
    }

    /**
     * @param  array<string, list<string>>  $places
     */
    private function canonicalPlace(string $value, array $places): string
    {
        $value = trim($value);
        foreach (array_keys($places) as $name) {
            if (strcasecmp($name, $value) === 0) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $barangays
     */
    private function canonicalBarangay(string $value, array $barangays): string
    {
        $value = trim($value);
        foreach ($barangays as $name) {
            if (strcasecmp($name, $value) === 0) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function searchWords(string $term): array
    {
        $words = preg_split('/[\s,]+/u', mb_strtolower(trim($term))) ?: [];

        return array_values(array_filter($words, fn (string $word) => $word !== ''));
    }
}
