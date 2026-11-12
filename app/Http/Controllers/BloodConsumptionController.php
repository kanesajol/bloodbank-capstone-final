<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionRecord;
use App\Support\BloodBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\View\View;

class BloodConsumptionController extends Controller
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

    /** @var list<string> */
    public const PROVINCES = [
        'Agusan del Norte',
        'Agusan del Sur',
        'Dinagat Islands',
        'Surigao del Norte',
        'Surigao del Sur',
    ];

    public function index(Request $request): View|JsonResponse
    {
        if ($request->boolean('returning_patients')) {
            $validated = $request->validate([
                'q' => ['nullable', 'string', 'max:100'],
            ]);

            return response()->json([
                'patients' => self::returningPatientNames((string) ($validated['q'] ?? '')),
            ]);
        }

        [$sdn, $outside] = self::placeGroups();
        $places = $sdn + $outside + array_fill_keys(array_keys(BloodBank::MUNICIPALITY_PROVINCE_MAP), []);
        $province = (string) $request->query('province', '');
        if (! in_array($province, self::PROVINCES, true)) {
            $province = '';
        }

        $municipality = $this->canonicalPlace((string) $request->query('municipality', ''), $places);
        if ($province === '' && isset(BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality])) {
            $province = BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality];
        }
        if ($municipality !== '' && $province !== '') {
            $municipalityProvince = BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality] ?? '';
            if ($municipalityProvince !== $province) {
                $municipality = '';
            }
        }

        $barangays = $municipality !== '' ? ($places[$municipality] ?? []) : [];
        if ($municipality !== '' && $barangays === []) {
            $barangays = ConsumptionRecord::query()
                ->where('municipality', $municipality)
                ->whereNotNull('barangay')
                ->where('barangay', '!=', '')
                ->distinct()
                ->orderBy('barangay')
                ->pluck('barangay')
                ->all();
        }
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

                $query->where(function ($places) use ($municipalities) {
                    $places->whereIn('municipality', $municipalities)
                        ->orWhere(function ($legacy) use ($municipalities) {
                            $legacy->whereNull('municipality')
                                ->whereIn('location', $municipalities);
                        });
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

        $currentYear = (int) now()->year;
        $list = (clone $filtered);
        $words = $this->searchWords($search);
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $list->whereRaw('LOWER(patient_name) like ?', [$like]);
        }
        $normalized = self::normalizedNameSql();

        if ($request->boolean('details')) {
            $validated = $request->validate([
                'patient' => ['required', 'string', 'max:255'],
            ]);
            $patientName = self::displayName($validated['patient']);
            $visits = (clone $list)
                ->whereRaw($normalized.' = ?', [$patientName])
                ->orderByDesc('date_submitted')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ConsumptionRecord $record) => $this->formatVisit(
                    $record,
                    $province,
                    $municipality,
                    $barangay,
                    $component,
                    $monthNumber,
                    $yearNumber,
                    $search
                ))
                ->values();

            abort_if($visits->isEmpty(), 404);

            if ($request->boolean('details_page')) {
                return view('blood-consumption-patient', [
                    'patientName' => $patientName,
                    'visits' => $visits,
                    'filters' => array_filter([
                        'province' => $province !== '' ? $province : null,
                        'municipality' => $municipality !== '' ? $municipality : null,
                        'barangay' => $barangay !== '' ? $barangay : null,
                        'component' => $component !== '' ? $component : null,
                        'month' => $monthNumber > 0 ? $monthNumber : null,
                        'year' => $yearNumber > 0 ? $yearNumber : null,
                        'q' => $search !== '' ? $search : null,
                    ]),
                ]);
            }

            return response()->json([
                'patient' => $patientName,
                'visits' => $visits,
            ]);
        }

        $matchTotal = (int) (clone $list)->selectRaw('COUNT(DISTINCT '.$normalized.') as aggregate')->value('aggregate');
        $names = (clone $list)->selectRaw($normalized.' as patient_name')->groupByRaw($normalized);
        if ($words !== []) {
            $first = $words[0];
            $names->selectRaw(
                'MIN(CASE WHEN LOWER('.$normalized.') LIKE ? THEN 0 WHEN LOWER('.$normalized.') LIKE ? THEN 1 ELSE 2 END) as name_rank',
                [$first.'%', '%, '.$first.'%']
            )->orderBy('name_rank');
        }
        $nameList = $names->orderByRaw($normalized)->limit(100)->pluck('patient_name');

        if ($request->boolean('search_json')) {
            return response()->json([
                'names' => $nameList->all(),
                'matchTotal' => $matchTotal,
                'visibleCount' => $nameList->count(),
                'limitNote' => $matchTotal > $nameList->count()
                    ? 'Showing '.number_format($nameList->count()).' of '.number_format($matchTotal).'. Type a name to narrow the list.'
                    : '',
            ]);
        }

        $totalUnits = (int) (clone $filtered)->sum('number_of_bags');
        $patients = $nameList->map(fn (string $name) => ['name' => $name]);

        $componentSums = (clone $filtered)
            ->whereIn('blood_component', self::COMPONENTS)
            ->select('blood_component')
            ->selectRaw('SUM(number_of_bags) as total')
            ->groupBy('blood_component')
            ->pluck('total', 'blood_component');

        $totals = [];
        foreach (self::COMPONENTS as $name) {
            $totals[] = [
                'component' => $name,
                'total' => (int) ($componentSums[$name] ?? 0),
            ];
        }

        $editingConsumptionId = old('form') === 'edit-consumption'
            ? (int) old('consumption_id', 0)
            : (int) $request->query('edit', 0);
        $editingConsumption = $editingConsumptionId > 0
            ? ConsumptionRecord::query()->findOrFail($editingConsumptionId)
            : null;

        return view('blood-consumption', [
            'patients' => $patients,
            'matchTotal' => $matchTotal,
            'recordCount' => ConsumptionRecord::query()->count(),
            'search' => $search,
            'totalsByComponent' => $totals,
            'totalUnits' => $totalUnits,
            'components' => self::COMPONENTS,
            'sdnMunicipalities' => $sdn,
            'outsidePlaces' => $outside,
            'provinces' => self::PROVINCES,
            'locationChoices' => $this->locationChoicesForForm(
                (string) old('province', $editingConsumption?->province ?? ''),
                (string) old('municipality', $editingConsumption?->municipality ?? ''),
                (string) old('barangay', $editingConsumption?->barangay ?? ''),
            ),
            'province' => $province,
            'barangays' => $barangays,
            'months' => BloodBank::MONTHS,
            'years' => [$currentYear - 1, $currentYear, $currentYear + 1],
            'municipality' => $municipality,
            'barangay' => $barangay,
            'component' => $component,
            'month' => $monthNumber > 0 ? (string) $monthNumber : '',
            'year' => $yearNumber > 0 ? (string) $yearNumber : '',
            'openModal' => old('form') === 'consumption' || $request->query('add') === '1',
            'editingConsumptionId' => $editingConsumptionId,
            'editingConsumption' => $editingConsumption,
            'formPage' => old('form') === 'consumption'
                || old('form') === 'edit-consumption'
                || $request->query('add') === '1'
                || $editingConsumptionId > 0,
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     patientName: string,
     *     date: string,
     *     age: int|string,
     *     sex: string,
     *     component: string,
     *     units: string,
     *     address: string,
     *     dateValue: string,
     *     ageValue: int|null,
     *     birthdate: string,
     *     birthdateValue: string,
     *     sexValue: string|null,
     *     componentValue: string|null,
     *     unitsValue: int,
     *     provinceValue: string|null,
     *     municipalityValue: string|null,
     *     barangayValue: string|null,
     *     editUrl: string,
     *     deleteUrl: string
     * }
     */
    private function formatVisit(
        ConsumptionRecord $record,
        string $province,
        string $municipality,
        string $barangay,
        string $component,
        int $month,
        int $year,
        string $search
    ): array {
        $address = $record->location
            ? BloodBank::expandLocationAbbreviations($record->location)
            : BloodBank::formatAssignedAddress($record->barangay, $record->municipality, $record->province);
        $resolvedProvince = $record->province ?: BloodBank::resolveCaragaLocation($address, $record->municipality)['province'];

        if (
            $resolvedProvince !== 'Unidentified Province'
            && ! str_contains(mb_strtolower($address), mb_strtolower($resolvedProvince))
        ) {
            $address = trim($address.', '.$resolvedProvince, ', ');
        }

        $filters = array_filter([
            'province' => $province !== '' ? $province : null,
            'municipality' => $municipality !== '' ? $municipality : null,
            'barangay' => $barangay !== '' ? $barangay : null,
            'component' => $component !== '' ? $component : null,
            'month' => $month > 0 ? $month : null,
            'year' => $year > 0 ? $year : null,
            'q' => $search !== '' ? $search : null,
        ]);

        return [
            'id' => $record->id,
            'patientName' => $record->patient_name,
            'date' => BloodBank::formatDate($record->date_submitted),
            'age' => $record->age ?? '—',
            'sex' => $record->sex ?: '—',
            'component' => $record->blood_component ?: ($record->blood_type ?: '—'),
            'units' => number_format((int) $record->number_of_bags),
            'address' => $address,
            'dateValue' => $record->date_submitted?->format('Y-m-d') ?? '',
            'ageValue' => $record->age,
            'birthdate' => $record->birthdate?->format('F j, Y') ?? '—',
            'birthdateValue' => $record->birthdate?->format('Y-m-d') ?? '',
            'sexValue' => $record->sex,
            'componentValue' => $record->blood_component,
            'unitsValue' => (int) $record->number_of_bags,
            'provinceValue' => $record->province,
            'municipalityValue' => $record->municipality,
            'barangayValue' => $record->barangay,
            'editUrl' => route('consumption.update', ['consumption' => $record->id] + $filters),
            'deleteUrl' => route('consumption.destroy', ['consumption' => $record->id] + $filters),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['patient_mode' => $request->input('patient_mode', 'new')]);
        $patientMode = (string) $request->input('patient_mode');
        $isReturningPatient = $patientMode === 'returning';
        $places = $this->places();
        $provinceInput = (string) $request->input('province', '');
        $allowedMunicipalities = $this->municipalitiesForProvince($provinceInput);
        $usesNameParts = $request->exists('first_name')
            || $request->exists('last_name')
            || $request->exists('middle_name')
            || $request->exists('suffix');
        $validated = $request->validate([
            'patient_mode' => ['required', Rule::in(['new', 'returning'])],
            'returning_patient_name' => [Rule::requiredIf($isReturningPatient), 'nullable', 'string', 'max:255'],
            'date_submitted' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'date'],
            ...(! $isReturningPatient && $usesNameParts ? [
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'suffix' => ['nullable', 'string', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV'])],
            ] : []),
            ...(! $isReturningPatient && ! $usesNameParts ? [
                'patient_name' => ['required', 'string', 'max:255'],
            ] : []),
            'age' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'integer', 'min:0', 'max:120'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => [Rule::requiredIf(! $isReturningPatient), 'nullable', Rule::in(['M', 'F'])],
            'blood_component' => ['required', Rule::in(self::COMPONENTS)],
            'units' => ['required', 'integer', 'min:1'],
            'municipality' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'string', 'max:255', Rule::in($allowedMunicipalities)],
            'province' => [Rule::requiredIf(! $isReturningPatient), 'nullable', 'string', 'max:255', Rule::in(self::PROVINCES)],
            'barangay' => $this->barangayRules((string) $request->input('municipality', '')),
        ]);

        if ($isReturningPatient) {
            $record = self::createReturningConsumption(
                $validated['returning_patient_name'],
                $validated['blood_component'],
                (int) $validated['units'],
            );

            if ($record === null) {
                return back()
                    ->withErrors(['returning_patient_name' => 'Select a patient with an existing consumption record.'])
                    ->withInput();
            }

            return redirect()
                ->route('blood-consumption')
                ->with('success', 'Returning patient consumption record saved.');
        }

        $municipality = $this->canonicalPlace($validated['municipality'], $places);
        if ($municipality === '') {
            $municipality = trim($validated['municipality']);
        }

        $barangayList = $places[$municipality] ?? [];
        $barangay = $this->canonicalBarangay((string) ($validated['barangay'] ?? ''), $barangayList);
        if ($barangay === '') {
            $barangay = trim((string) ($validated['barangay'] ?? ''));
        }
        $province = $this->assignedProvince($municipality, (string) $validated['province']);

        $submitted = Carbon::parse($validated['date_submitted']);
        $patientName = $usesNameParts
            ? DonorController::displayName(
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['suffix'] ?? null,
            )
            : self::displayName($validated['patient_name']);
        $record = new ConsumptionRecord;
        $record->forceFill([
            'patient_name' => $patientName,
            'age' => (int) $validated['age'],
            'birthdate' => $validated['birthdate'] ?? null,
            'sex' => $validated['sex'],
            'blood_component' => $validated['blood_component'],
            'municipality' => $municipality,
            'province' => $province,
            'barangay' => $barangay !== '' ? $barangay : null,
            'location' => BloodBank::cleanAddressString(implode(', ', array_filter([
                $barangay,
                $municipality,
                $province,
            ]))),
            'blood_type' => null,
            'number_of_bags' => (int) $validated['units'],
            'month' => (int) $submitted->month,
            'year' => (int) $submitted->year,
            'reporting_period' => BloodBank::MONTHS[$submitted->month - 1].' '.$submitted->year,
            'date_submitted' => $validated['date_submitted'],
        ])->save();

        return redirect()
            ->route('blood-consumption', array_filter([
                'municipality' => $municipality,
                'barangay' => $barangay !== '' ? $barangay : null,
            ]))
            ->with('success', 'Consumption record saved.');
    }

    public function destroy(Request $request, ConsumptionRecord $consumption): RedirectResponse
    {
        $label = $consumption->patient_name ?: $consumption->location;
        $consumption->delete();

        if ((string) $request->query('return_to') === 'reports') {
            return redirect()
                ->route('reports', array_filter([
                    'view' => 'consumption',
                    'location' => $request->query('report_location'),
                    'blood_type' => $request->query('blood_type'),
                    'consumption_month' => $request->query('consumption_month'),
                    'consumption_year' => $request->query('consumption_year'),
                ]))
                ->with('success', "Consumption record for {$label} was deleted.");
        }

        return redirect()
            ->route('blood-consumption', array_filter([
                'municipality' => $request->query('municipality'),
                'barangay' => $request->query('barangay'),
                'component' => $request->query('component'),
                'month' => $request->query('month'),
                'year' => $request->query('year'),
                'q' => $request->query('q'),
            ]))
            ->with('success', "Consumption record for {$label} was deleted.");
    }

    public function update(Request $request, ConsumptionRecord $consumption): RedirectResponse
    {
        $allowedMunicipalities = $this->municipalitiesForProvince((string) $request->input('province', ''));
        if ($consumption->municipality && ! in_array($consumption->municipality, $allowedMunicipalities, true)) {
            $allowedMunicipalities[] = $consumption->municipality;
        }
        $validated = $request->validate([
            'consumption_id' => ['required', 'integer', 'in:'.$consumption->id],
            'patient_name' => ['required', 'string', 'max:255'],
            'date_submitted' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['M', 'F'])],
            'blood_component' => ['required', Rule::in(self::COMPONENTS)],
            'units' => ['required', 'integer', 'min:1'],
            'municipality' => ['required', 'string', 'max:255', Rule::in($allowedMunicipalities)],
            'province' => ['required', 'string', 'max:255', Rule::in(self::PROVINCES)],
            'barangay' => $this->barangayRules((string) $request->input('municipality', ''), $consumption->barangay),
        ]);

        $places = $this->places();
        $municipality = $this->canonicalPlace($validated['municipality'], $places);
        if ($municipality === '') {
            $municipality = trim($validated['municipality']);
        }

        $barangay = trim((string) ($validated['barangay'] ?? ''));
        $barangay = $this->canonicalBarangay($barangay, $places[$municipality] ?? []) ?: $barangay;
        $province = $this->assignedProvince($municipality, (string) $validated['province']);
        $submitted = Carbon::parse($validated['date_submitted']);

        $consumption->forceFill([
            'patient_name' => self::displayName($validated['patient_name']),
            'date_submitted' => $validated['date_submitted'],
            'age' => (int) $validated['age'],
            'birthdate' => $validated['birthdate'] ?? null,
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

        return redirect()
            ->route('blood-consumption', array_filter([
                'province' => $request->query('province'),
                'municipality' => $request->query('municipality', $municipality),
                'barangay' => $request->query('barangay', $barangay !== '' ? $barangay : null),
                'component' => $request->query('component'),
                'month' => $request->query('month'),
                'year' => $request->query('year'),
                'q' => $request->query('q'),
            ]))
            ->with('success', 'Consumption record updated.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $count = ConsumptionRecord::query()->count();
        ConsumptionRecord::query()->delete();

        return redirect()
            ->route('blood-consumption', array_filter([
                'province' => $request->query('province'),
                'municipality' => $request->query('municipality'),
                'barangay' => $request->query('barangay'),
                'component' => $request->query('component'),
                'month' => $request->query('month'),
                'year' => $request->query('year'),
                'q' => $request->query('q'),
            ]))
            ->with('success', $count === 1 ? '1 consumption record was deleted.' : "{$count} consumption records were deleted.");
    }

    /**
     * @return array<string, list<string>>
     */
    public static function places(): array
    {
        [$sdn, $outside] = self::placeGroups();

        return $sdn + $outside;
    }

    /**
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    public static function placeGroups(): array
    {
        $sdn = [
            'Alegria' => ['Alipao', 'Anahaw', 'Budlingin', 'Camp Eduard (Geotina)', 'Ferlda', 'Gamuton', 'Julio Ouano', 'Ombong', 'Poblacion', 'Pongtud', 'San Juan', 'San Pedro'],
            'Bacuag' => ['Cabugao', 'Cambuayon', 'Campo', 'Dugsangon', 'Pautao', 'Payapag', 'Poblacion', 'Pungtod', 'Santo Rosario'],
            'Burgos' => ['Baybay', 'Bitaug', 'Matin-ao', 'Poblacion 1', 'Poblacion 2', 'San Mateo'],
            'Claver' => ['Bagakay (West Poblacion)', 'Cabugo', 'Cagdianao', 'Daywan', 'Hayanggabon', 'Ladgaron (Poblacion)', 'Lapinigan', 'Magallanes', 'Panatao', 'Sapa', 'Taganito', 'Tayaga (Pob. East)', 'Urbiztondo', 'Wangke'],
            'Dapa' => ['Bagakay', 'Barangay 1 (Poblacion)', 'Barangay 2 (Poblacion)', 'Barangay 3 (Poblacion)', 'Barangay 4 (Poblacion)', 'Barangay 5 (Poblacion)', 'Barangay 6 (Poblacion)', 'Barangay 7 (Poblacion)', 'Barangay 8 (Poblacion)', 'Barangay 9 (Poblacion)', 'Barangay 10 (Poblacion)', 'Barangay 11 (Poblacion)', 'Barangay 12 (Poblacion)', 'Barangay 13 (Poblacion)', 'Buenavista', 'Cabawa', 'Cambas-ac', 'Consolacion', 'Corregidor', 'Dagohoy', 'Don Paulino', 'Jubang', 'Montserrat', 'Osmeña', 'San Carlos', 'San Miguel', 'Santa Fe', 'Santa Felomina', 'Union'],
            'Del Carmen' => ['Alburo (Bagakay)', 'Antipolo', 'Bitoon', 'Cabugao', 'Cancohoy', 'Caub', 'Del Carmen (Poblacion)', 'Domoyog', 'Esperanza', 'Halian', 'Jamoyaon', 'Katipunan', 'Lobogon', 'Mabuhay', 'Mahayahay', 'Quezon', 'San Fernando', 'San Jose (Poblacion)', 'Sayak', 'Tuboran'],
            'General Luna' => ['Anajawan', 'Cabitoonan', 'Catangnan', 'Consuelo', 'Corazon', 'Daku', 'La Januza', 'Libertad', 'Magsaysay', 'Malinao', 'Poblacion I (Purok I)', 'Poblacion II (Purok II)', 'Poblacion III (Purok III)', 'Poblacion IV (Purok IV)', 'Poblacion V (Purok V)', 'Santa Cruz', 'Santa Fe', 'Suyangan', 'Tawin-tawin'],
            'Gigaquit' => ['Alambique (Poblacion)', 'Anibongan', 'Cam-boayon', 'Camam-onan', 'Ipil (Poblacion)', 'Lahi', 'Mahanub', 'Poniente', 'San Antonio (Bonot)', 'San Isidro (Parang)', 'Sico-sico', 'Villaflor', 'Villafranca'],
            'Mainit' => ['Binga', 'Bobona-on', 'Cantugas', 'Dayano', 'Mabini', 'Magpayang', 'Magsaysay (Poblacion)', 'Mansayao', 'Marayag', 'Matin-ao', 'Paco', 'Quezon (Poblacion)', 'Roxas', 'San Francisco', 'San Isidro', 'San Jose', 'Siana', 'Silop', 'Tagbuyawan', 'Tapi-an', 'Tolingon'],
            'Malimono' => ['Bunyasan', 'Cagtinae', 'Can-aga', 'Cansayong', 'Cantapoy', 'Cayawan', 'Doro (Binocaran)', 'Hanagdong', 'Karihatag', 'Masgad', 'Pili', 'San Isidro (Poblacion)', 'Tinago', 'Villariza'],
            'Pilar' => ['Asinan (Poblacion)', 'Caridad', 'Centro (Poblacion)', 'Consolacion', 'Datu', 'Dayaohay', 'Jaboy', 'Katipunan', 'Maasin', 'Mabini', 'Mabuhay', 'Pilaring (Poblacion)', 'Punta (Poblacion)', 'Salvacion', 'San Roque'],
            'Placer' => ['Amoslog', 'Anislagan', 'Bad-as', 'Boyongan', 'Bugas-bugas', 'Central (Poblacion)', 'Ellaperal (Nonok)', 'Ipil (Poblacion)', 'Lakandula', 'Mabini', 'Macalaya', 'Magsaysay (Poblacion)', 'Magupange', 'Pananay-an', 'Panhutongan', 'San Isidro', 'Sani-sani', 'Santa Cruz', 'Suyoc', 'Tagbongabong'],
            'San Benito' => ['Bongdo', 'Maribojoc', 'Nuevo Campo', 'San Juan', 'Santa Cruz (Poblacion)', 'Talisay (Poblacion)'],
            'San Francisco' => ['Amontay', 'Balite', 'Banbanon', 'Diaz', 'Honrado', 'Jubgan', 'Linongganan', 'Macopa', 'Magtangale', 'Oslao', 'Poblacion'],
            'San Isidro' => ['Buhing Calipay', 'Del Carmen (Poblacion)', 'Del Pilar', 'Macapagal', 'Pacifico', 'Pelaez', 'Roxas', 'San Miguel', 'Santa Paz', 'Santo Niño', 'Tambacan', 'Tigasao'],
            'Santa Monica' => ['Abad Santos', 'Alegria', 'Bailan', 'Garcia', 'Libertad', 'Mabini', 'Mabuhay (Poblacion)', 'Magsaysay', 'Rizal', 'T. Arlan (Poblacion)', 'Tangbo'],
            'Sison' => ['Biyabid', 'Gacepan', 'Ima', 'Lower Patag', 'Mabuhay', 'Mayag', 'Poblacion (San Pedro)', 'San Isidro', 'San Pablo', 'Tagbayani', 'Tinogpahan', 'Upper Patag'],
            'Socorro' => ['Del Pilar', 'Don Albino Taruc (Poblacion)', 'Doña Helene', 'Honrado', 'Navarro (Poblacion)', 'Nueva Estrella', 'Pamosaingan', 'Rizal (Poblacion)', 'Salog', 'San Roque', 'Santa Cruz', 'Sering', 'Songkoy', 'Sudlon'],
            'Surigao City' => ['Alang-alang', 'Alegria', 'Anomar', 'Aurora', 'Balibayon', 'Baybay', 'Bilabid', 'Bitaugan', 'Bonifacio', 'Buenavista', 'Cabongbongan', 'Cagniog', 'Cagutsan', 'Canlanipa', 'Cantiasay', 'Capalayan', 'Catadman', 'Danao', 'Danawan', 'Day-asan', 'Ipil', 'Libuac', 'Lipata', 'Lisondra', 'Luna', 'Mabini', 'Mabua', 'Manjagao', 'Mapawa', 'Mat-i', 'Nabago', 'Nonoc', 'Orok', 'Poctoy', 'Punta Bilar', 'Quezon', 'Rizal', 'Sabang', 'San Isidro', 'San Jose', 'San Juan', 'San Pedro (Hanigad)', 'San Roque', 'Serna (Bad-asay)', 'Sidlakan', 'Silop', 'Sugbay', 'Sukailang', 'Taft', 'Talisay', 'Togbongon', 'Trinidad', 'Washington', 'Zaragoza'],
            'Tagana-an' => ['Aurora (Poblacion)', 'Azucena (Poblacion)', 'Banban', 'Cawilan', 'Fabio', 'Himamaug', 'Laurel', 'Lower Libas', 'Opong', 'Patiño', 'Sampaguita (Poblacion)', 'Talavera', 'Union', 'Upper Libas'],
            'Tubod' => ['Capayahan', 'Cawilan', 'Del Rosario', 'Marga', 'Motorpool', 'Poblacion (Tubod)', 'San Isidro', 'San Pablo', 'Timamana'],
        ];

        foreach ($sdn as $name => $barangays) {
            sort($barangays, SORT_NATURAL | SORT_FLAG_CASE);
            $sdn[$name] = $barangays;
        }
        ksort($sdn, SORT_NATURAL | SORT_FLAG_CASE);

        $outside = [
            'AGUSAN DEL NORTE' => [],
            'AGUSAN DEL SUR' => [],
            'SURIGAO DEL SUR' => [],
            'OUTSIDE' => [],
        ];

        return [$sdn, $outside];
    }

    /**
     * @return array{municipalitiesByProvince: array<string, list<string>>, places: array<string, list<string>>}
     */
    public static function formLocationChoices(): array
    {
        $places = self::places()
            + array_fill_keys(array_keys(BloodBank::MUNICIPALITY_PROVINCE_MAP), []);

        $storedPlaces = ConsumptionRecord::query()
            ->select('municipality', 'barangay')
            ->whereNotNull('municipality')
            ->where('municipality', '!=', '')
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->get();

        foreach ($storedPlaces as $record) {
            if (! isset($places[$record->municipality])) {
                continue;
            }

            if (! in_array($record->barangay, $places[$record->municipality], true)) {
                $places[$record->municipality][] = $record->barangay;
            }
        }

        foreach ($places as &$barangays) {
            sort($barangays, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($barangays);

        $municipalitiesByProvince = [];
        foreach (BloodBank::MUNICIPALITY_PROVINCE_MAP as $municipality => $province) {
            $municipalitiesByProvince[$province][] = $municipality;
        }
        foreach ($municipalitiesByProvince as &$municipalities) {
            sort($municipalities, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($municipalities);

        return [
            'municipalitiesByProvince' => $municipalitiesByProvince,
            'places' => $places,
        ];
    }

    /**
     * @return array{municipalitiesByProvince: array<string, list<string>>, places: array<string, list<string>>}
     */
    private function locationChoicesForForm(string $province, string $municipality, string $barangay): array
    {
        $choices = self::formLocationChoices();
        if ($province !== '' && $municipality !== '') {
            $choices['municipalitiesByProvince'][$province] ??= [];
            if (! in_array($municipality, $choices['municipalitiesByProvince'][$province], true)) {
                $choices['municipalitiesByProvince'][$province][] = $municipality;
            }
        }
        if ($municipality !== '' && $barangay !== '') {
            $choices['places'][$municipality] ??= [];
            if (! in_array($barangay, $choices['places'][$municipality], true)) {
                $choices['places'][$municipality][] = $barangay;
            }
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    private function municipalitiesForProvince(string $province): array
    {
        return array_keys(array_filter(
            BloodBank::MUNICIPALITY_PROVINCE_MAP,
            fn (string $mappedProvince): bool => $mappedProvince === $province
        ));
    }

    /**
     * @return list<string|In>
     */
    private function barangayRules(string $municipality, ?string $current = null): array
    {
        $rules = ['nullable', 'string', 'max:255'];
        $places = self::places();
        $canonical = $this->canonicalPlace($municipality, $places);
        $barangays = $places[$canonical] ?? [];
        if ($current !== null && $current !== '' && ! in_array($current, $barangays, true)) {
            $barangays[] = $current;
        }
        if ($barangays !== []) {
            $rules[] = Rule::in($barangays);
        }

        return $rules;
    }

    private function assignedProvince(string $municipality, string $submitted): string
    {
        $candidate = BloodBank::MUNICIPALITY_PROVINCE_MAP[$municipality] ?? trim($submitted);
        foreach (self::PROVINCES as $name) {
            if (strcasecmp($name, $candidate) === 0) {
                return $name;
            }
        }

        return $candidate;
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

        return $value;
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

    public static function displayName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/\s*,\s*/u', ', ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return $name;
    }

    /**
     * @return list<string>
     */
    public static function returningPatientNames(string $search): array
    {
        $words = preg_split('/[\s,]+/u', mb_strtolower(trim($search))) ?: [];
        $words = array_values(array_filter($words, fn (string $word): bool => $word !== ''));
        if ($words === []) {
            return [];
        }

        $normalized = self::normalizedNameSql();
        $patients = ConsumptionRecord::query()
            ->whereNotNull('patient_name')
            ->where('patient_name', '!=', '')
            ->selectRaw($normalized.' as patient_name')
            ->groupByRaw($normalized);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $patients->whereRaw('LOWER('.$normalized.') like ?', [$like]);
        }

        return $patients
            ->orderByRaw($normalized)
            ->limit(20)
            ->pluck('patient_name')
            ->map(fn (string $name): string => self::displayName($name))
            ->all();
    }

    public static function createReturningConsumption(string $patientName, string $component, int $units): ?ConsumptionRecord
    {
        $normalized = self::normalizedNameSql();
        $latestRecord = ConsumptionRecord::query()
            ->whereNotNull('patient_name')
            ->whereRaw($normalized.' = ?', [self::displayName($patientName)])
            ->orderByDesc('date_submitted')
            ->orderByDesc('id')
            ->first();

        if ($latestRecord === null) {
            return null;
        }

        $submitted = now();
        $record = new ConsumptionRecord;
        $record->forceFill([
            'patient_name' => self::displayName((string) $latestRecord->patient_name),
            'age' => $latestRecord->age,
            'birthdate' => $latestRecord->birthdate,
            'sex' => $latestRecord->sex,
            'blood_component' => $component,
            'municipality' => $latestRecord->municipality,
            'province' => $latestRecord->province,
            'barangay' => $latestRecord->barangay,
            'location' => $latestRecord->location,
            'blood_type' => $latestRecord->blood_type,
            'number_of_bags' => $units,
            'month' => (int) $submitted->month,
            'year' => (int) $submitted->year,
            'reporting_period' => BloodBank::MONTHS[$submitted->month - 1].' '.$submitted->year,
            'date_submitted' => $submitted->toDateString(),
        ])->save();

        return $record;
    }

    private static function normalizedNameSql(): string
    {
        return "TRIM(REGEXP_REPLACE(REGEXP_REPLACE(UPPER(patient_name), '[[:space:]]*,[[:space:]]*', ', '), '[[:space:]]+', ' '))";
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
