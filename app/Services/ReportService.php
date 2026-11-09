<?php

namespace App\Services;

use App\Models\ConsumptionRecord;
use App\Models\Donation;
use App\Models\Donor;
use App\Support\BloodBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function locations(): array
    {
        $origins = Donor::query()
            ->get(['barangay', 'municipality', 'province'])
            ->flatMap(function (Donor $donor) {
                return [
                    $donor->barangay,
                    $donor->municipality,
                    $donor->province ?: BloodBank::provinceForMunicipality($donor->municipality),
                    BloodBank::formatAssignedAddress($donor->barangay, $donor->municipality, $donor->province),
                ];
            });

        $fromConsumption = ConsumptionRecord::query()
            ->select(['location', 'barangay', 'municipality', 'province'])
            ->distinct()
            ->get()
            ->flatMap(fn (ConsumptionRecord $record) => [
                $record->location,
                $record->barangay,
                $record->municipality,
                $record->province ?: BloodBank::provinceForMunicipality($record->municipality),
                BloodBank::formatAssignedAddress($record->barangay, $record->municipality, $record->province),
            ]);

        return $origins
            ->merge($fromConsumption)
            ->map(fn ($location) => trim((string) $location))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function monthly(int $month, int $year, string $location = ''): array
    {
        $period = Donation::query()
            ->join('donors', 'donors.id', '=', 'donations.donor_id')
            ->whereYear('donations.donated_on', $year)
            ->whereMonth('donations.donated_on', $month)
            ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location));

        $rows = (clone $period)
            ->groupBy(
                'donors.id',
                'donors.full_name',
                'donors.blood_type',
                'donors.age',
                'donors.gender',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            )
            ->select([
                'donors.id as donor_id',
                'donors.full_name',
                'donors.blood_type',
                'donors.age',
                'donors.gender',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            ])
            ->selectRaw('COUNT(DISTINCT donations.id) as donation_count')
            ->selectRaw('MAX(donations.donated_on) as latest_donation')
            ->orderBy('donors.full_name')
            ->get();

        $history = $rows->isEmpty()
            ? collect()
            : Donation::query()
                ->whereIn('donor_id', $rows->pluck('donor_id'))
                ->orderByDesc('donated_on')
                ->orderByDesc('id')
                ->get()
                ->groupBy('donor_id');

        $donors = $rows->map(function ($row) use ($history) {
            $visits = $history->get($row->donor_id, collect());

            return [
                'donor_id' => (int) $row->donor_id,
                'donor_name' => $row->full_name,
                'blood_type' => $row->blood_type,
                'age' => (int) $row->age,
                'gender' => $row->gender,
                'contact' => $row->contact,
                'location' => BloodBank::formatAssignedAddress($row->barangay, $row->municipality, $row->province),
                'date' => $row->latest_donation,
                'donation_count' => (int) $row->donation_count,
                'lifetime_count' => $visits->count(),
                'history' => $visits->map(fn (Donation $item) => [
                    'date' => $item->donated_on->toDateString(),
                    'location' => $item->location,
                    'blood_type' => $item->blood_type ?: $row->blood_type,
                    'bags' => $item->bags ?: 1,
                ])->values()->all(),
            ];
        })->values();

        $breakdown = (clone $period)
            ->groupBy('donors.blood_type')
            ->select('donors.blood_type')
            ->selectRaw('COUNT(donations.id) as donation_count')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->blood_type,
                'count' => (int) $row->donation_count,
            ])
            ->values()
            ->all();

        return [
            'donations' => $donors,
            'bloodTypeBreakdown' => $breakdown,
            'uniqueDonors' => $donors->count(),
        ];
    }

    public function quarterly(int $year, string $location = ''): array
    {
        $quarters = [
            ['quarter' => 'Q1', 'months' => [1, 2, 3], 'periodLabel' => 'January to March'],
            ['quarter' => 'Q2', 'months' => [4, 5, 6], 'periodLabel' => 'April to June'],
            ['quarter' => 'Q3', 'months' => [7, 8, 9], 'periodLabel' => 'July to September'],
            ['quarter' => 'Q4', 'months' => [10, 11, 12], 'periodLabel' => 'October to December'],
        ];

        return collect($quarters)->map(function (array $quarter) use ($year, $location) {
            $newDonors = Donor::query()
                ->with('donations')
                ->whereYear('registered_date', $year)
                ->where(function (Builder $query) use ($quarter) {
                    $query->whereMonth('registered_date', $quarter['months'][0]);
                    foreach (array_slice($quarter['months'], 1) as $month) {
                        $query->orWhereMonth('registered_date', $month);
                    }
                })
                ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location))
                ->orderBy('full_name')
                ->get();

            return [
                ...$quarter,
                'newDonors' => $newDonors,
            ];
        })->all();
    }

    public function yearly(int $year, string $location = ''): array
    {
        $donations = Donation::query()
            ->join('donors', 'donors.id', '=', 'donations.donor_id')
            ->whereYear('donations.donated_on', $year)
            ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location));

        $totalDonors = $this->countDistinctDonorIds(clone $donations);
        $totalDonations = (int) (clone $donations)->sum('donations.bags');

        $newDonorsCount = $this->countDistinctIds(
            Donor::query()
                ->whereYear('registered_date', $year)
                ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location)),
            'donors.id'
        );

        $repeatDonorsCount = $this->countDistinctIds(
            Donor::query()
                ->whereIn('donors.id', (clone $donations)->select('donations.donor_id'))
                ->whereIn('donors.id', function ($query) {
                    $query->select('donor_id')
                        ->from('donations')
                        ->groupBy('donor_id')
                        ->havingRaw('COUNT(donations.id) > 1');
                })
                ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location)),
            'donors.id'
        );

        $byLocation = (clone $donations)
            ->groupBy('donors.barangay', 'donors.municipality', 'donors.province')
            ->select('donors.barangay', 'donors.municipality', 'donors.province')
            ->selectRaw('COUNT(DISTINCT donations.donor_id) as donor_count')
            ->selectRaw('COALESCE(SUM(donations.bags), 0) as donation_count')
            ->get()
            ->map(function ($row) use ($totalDonations) {
                $bags = (int) $row->donation_count;

                return [
                    'name' => BloodBank::formatAssignedAddress($row->barangay, $row->municipality, $row->province),
                    'donorCount' => (int) $row->donor_count,
                    'donationCount' => $bags,
                    'percentage' => $totalDonations ? (int) round(($bags / $totalDonations) * 100) : 0,
                ];
            })
            ->groupBy('name')
            ->map(function ($locations, string $name) use ($totalDonations) {
                $bags = (int) $locations->sum('donationCount');

                return [
                    'name' => $name,
                    'donorCount' => (int) $locations->sum('donorCount'),
                    'donationCount' => $bags,
                    'percentage' => $totalDonations ? (int) round(($bags / $totalDonations) * 100) : 0,
                ];
            })
            ->sortByDesc('donationCount')
            ->values()
            ->all();

        $bloodRows = (clone $donations)
            ->groupBy('donors.blood_type')
            ->select('donors.blood_type')
            ->selectRaw('COUNT(DISTINCT donations.donor_id) as donor_count')
            ->selectRaw('COALESCE(SUM(donations.bags), 0) as bags')
            ->get()
            ->keyBy('blood_type');

        $byBloodType = [];
        foreach (BloodBank::BLOOD_TYPES as $type) {
            $row = $bloodRows->get($type);
            $count = (int) ($row->donor_count ?? 0);
            $byBloodType[] = [
                'type' => $type,
                'count' => $count,
                'bags' => (int) ($row->bags ?? 0),
                'percentage' => $totalDonors ? (int) round(($count / $totalDonors) * 100) : 0,
            ];
        }

        $ageSelects = [];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $ageSelects[] = "COUNT(DISTINCT CASE WHEN donors.age BETWEEN {$bracket['min']} AND {$bracket['max']} THEN donors.id END) as bracket_{$index}";
        }

        $ageRow = Donor::query()
            ->whereIn('donors.id', (clone $donations)->select('donations.donor_id'))
            ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location))
            ->selectRaw(implode(', ', $ageSelects))
            ->first();

        $byAgeGroup = [];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $column = 'bracket_'.$index;
            $count = (int) ($ageRow->{$column} ?? 0);
            $byAgeGroup[] = [
                'group' => $bracket['group'],
                'count' => $count,
                'percentage' => $totalDonors ? (int) round(($count / $totalDonors) * 100) : 0,
            ];
        }

        $genderRow = Donor::query()
            ->whereIn('donors.id', (clone $donations)->select('donations.donor_id'))
            ->tap(fn (Builder $query) => $this->constrainDonorLocation($query, $location))
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(donors.gender) = 'male' THEN donors.id END) as male_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(donors.gender) = 'female' THEN donors.id END) as female_count")
            ->first();

        $maleCount = (int) ($genderRow->male_count ?? 0);
        $femaleCount = (int) ($genderRow->female_count ?? 0);
        $malePct = $totalDonors ? (int) round(($maleCount / $totalDonors) * 100) : 0;
        $femalePct = $totalDonors ? 100 - $malePct : 0;

        return [
            'totalDonors' => $totalDonors,
            'totalDonations' => $totalDonations,
            'newDonorsCount' => $newDonorsCount,
            'repeatDonorsCount' => $repeatDonorsCount,
            'byLocation' => $byLocation,
            'byBloodType' => $byBloodType,
            'byAgeGroup' => $byAgeGroup,
            'byGender' => [
                'male' => $maleCount,
                'female' => $femaleCount,
                'malePct' => $malePct,
                'femalePct' => $femalePct,
            ],
        ];
    }

    public function ageReport(string $year, string $location = '', string $month = 'all'): array
    {
        return $this->ageReportFromDonors($this->donorSummary($year, $month, $location));
    }

    /**
     * @return array{age: array, gender: array{total: int, rows: array}}
     */
    public function demographics(string $year, string $month, string $location = ''): array
    {
        $selects = ['COUNT(DISTINCT donors.id) as total_donors'];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $range = "donors.age BETWEEN {$bracket['min']} AND {$bracket['max']}";
            $selects[] = "COUNT(DISTINCT CASE WHEN {$range} THEN donors.id END) as age_count_{$index}";
            $selects[] = "COALESCE(SUM(CASE WHEN {$range} THEN CASE WHEN donations.bags IS NULL OR donations.bags = 0 THEN 1 ELSE donations.bags END ELSE 0 END), 0) as age_bags_{$index}";
        }
        foreach (['Female' => 'female', 'Male' => 'male'] as $gender => $alias) {
            $selects[] = "COUNT(DISTINCT CASE WHEN LOWER(donors.gender) = '".mb_strtolower($gender)."' THEN donors.id END) as {$alias}_count";
            $selects[] = "COALESCE(SUM(CASE WHEN LOWER(donors.gender) = '".mb_strtolower($gender)."' THEN CASE WHEN donations.bags IS NULL OR donations.bags = 0 THEN 1 ELSE donations.bags END ELSE 0 END), 0) as {$alias}_bags";
        }

        $summary = $this->donorSummaryQuery($year, $month, $location)
            ->selectRaw(implode(', ', $selects))
            ->first();

        $totalDonors = (int) ($summary->total_donors ?? 0);
        $ageGroups = [];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $ageGroups[] = [
                'group' => $bracket['group'],
                'count' => (int) ($summary->{'age_count_'.$index} ?? 0),
                'totalDonations' => (int) ($summary->{'age_bags_'.$index} ?? 0),
                'donors' => [],
            ];
        }

        $typeBags = $this->genderTypeBags((int) $year, $month, $location);
        $genderRows = [];
        foreach (['Female' => 'female', 'Male' => 'male'] as $genderName => $alias) {
            $bagsByType = [];
            foreach ($typeBags as $name => $typeTotals) {
                if (strcasecmp((string) $name, $genderName) === 0) {
                    foreach ($typeTotals as $bloodType => $bags) {
                        $bagsByType[$bloodType] = ($bagsByType[$bloodType] ?? 0) + $bags;
                    }
                }
            }
            arsort($bagsByType);
            $count = (int) ($summary->{$alias.'_count'} ?? 0);
            $genderRows[] = [
                'gender' => $genderName,
                'count' => $count,
                'percentage' => $totalDonors > 0 ? (int) round(($count / $totalDonors) * 100) : 0,
                'bags' => (int) ($summary->{$alias.'_bags'} ?? 0),
                'topBloodType' => $bagsByType === [] ? '—' : (string) array_key_first($bagsByType),
                'donors' => [],
            ];
        }

        return [
            'age' => [
                'totalDonors' => $totalDonors,
                'groups' => $ageGroups,
            ],
            'gender' => [
                'total' => $totalDonors,
                'rows' => $genderRows,
            ],
        ];
    }

    /**
     * @return array{
     *     age: array{totalPatients: int, groups: array<int, array{group: string, count: int, bags: int}>},
     *     gender: array{totalPatients: int, rows: array<int, array{gender: string, count: int, bags: int}>}
     * }
     */
    public function consumptionDemographics(string $month, string $year, string $location = ''): array
    {
        $patients = $this->consumptionDemographicPatientsQuery($month, $year, $location);
        $selects = ['COUNT(*) as total_patients'];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $range = "patients.age BETWEEN {$bracket['min']} AND {$bracket['max']}";
            $selects[] = "COUNT(CASE WHEN {$range} THEN 1 END) as age_count_{$index}";
            $selects[] = "COALESCE(SUM(CASE WHEN {$range} THEN patients.bags ELSE 0 END), 0) as age_bags_{$index}";
        }
        foreach (['Female' => "UPPER(patients.sex) IN ('F', 'FEMALE')", 'Male' => "UPPER(patients.sex) IN ('M', 'MALE')"] as $gender => $condition) {
            $alias = mb_strtolower($gender);
            $selects[] = "COUNT(CASE WHEN {$condition} THEN 1 END) as {$alias}_count";
            $selects[] = "COALESCE(SUM(CASE WHEN {$condition} THEN patients.bags ELSE 0 END), 0) as {$alias}_bags";
        }

        $summary = DB::query()
            ->fromSub($patients, 'patients')
            ->selectRaw(implode(', ', $selects))
            ->first();

        $ageGroups = [];
        foreach ($this->ageBracketDefinitions() as $index => $bracket) {
            $ageGroups[] = [
                'group' => $bracket['group'],
                'count' => (int) ($summary->{'age_count_'.$index} ?? 0),
                'bags' => (int) ($summary->{'age_bags_'.$index} ?? 0),
            ];
        }

        $genderRows = [];
        foreach (['Female' => 'female', 'Male' => 'male'] as $gender => $alias) {
            $genderRows[] = [
                'gender' => $gender,
                'count' => (int) ($summary->{$alias.'_count'} ?? 0),
                'bags' => (int) ($summary->{$alias.'_bags'} ?? 0),
            ];
        }

        return [
            'age' => [
                'totalPatients' => (int) ($summary->total_patients ?? 0),
                'groups' => $ageGroups,
            ],
            'gender' => [
                'totalPatients' => (int) ($summary->total_patients ?? 0),
                'rows' => $genderRows,
            ],
        ];
    }

    /**
     * @return array<int, array{patient_name: string, age: int|null, sex: string|null, bags: int}>
     */
    public function consumptionDemographicPatients(
        string $month,
        string $year,
        string $location,
        string $type,
        string $group
    ): array {
        $query = DB::query()
            ->fromSub($this->consumptionDemographicPatientsQuery($month, $year, $location), 'patients');

        if ($type === 'age') {
            $bracket = collect($this->ageBracketDefinitions())->firstWhere('group', $group);
            abort_if($bracket === null, 404);
            $query->whereBetween('patients.age', [$bracket['min'], $bracket['max']]);
        } elseif ($type === 'gender' && in_array($group, ['Female', 'Male'], true)) {
            $sexValues = $group === 'Female' ? ['F', 'FEMALE'] : ['M', 'MALE'];
            $query->whereIn(DB::raw('UPPER(patients.sex)'), $sexValues);
        } else {
            abort(404);
        }

        return $query
            ->select(['patients.patient_name', 'patients.age', 'patients.sex', 'patients.bags'])
            ->orderBy('patients.patient_name')
            ->get()
            ->map(function ($patient): array {
                $sex = strtoupper(trim((string) $patient->sex));

                return [
                    'patient_name' => trim((string) $patient->patient_name),
                    'age' => $patient->age === null ? null : (int) $patient->age,
                    'sex' => match ($sex) {
                        'F', 'FEMALE' => 'Female',
                        'M', 'MALE' => 'Male',
                        default => $patient->sex,
                    },
                    'bags' => (int) $patient->bags,
                ];
            })
            ->all();
    }

    private function consumptionDemographicPatientsQuery(string $month, string $year, string $location): \Illuminate\Database\Query\Builder
    {
        $patientTotals = $this->consumptionRecordsQuery($location, '', $month, $year)
            ->whereNotNull('patient_name')
            ->whereRaw("TRIM(patient_name) <> ''")
            ->selectRaw('LOWER(TRIM(patient_name)) as patient_key, MIN(patient_name) as patient_name, MAX(id) as latest_record_id, SUM(number_of_bags) as bags')
            ->groupByRaw('LOWER(TRIM(patient_name))')
            ->toBase();

        return DB::query()
            ->fromSub($patientTotals, 'patient_totals')
            ->join('consumption_records as latest_record', 'latest_record.id', '=', 'patient_totals.latest_record_id')
            ->select([
                'patient_totals.patient_key',
                'patient_totals.patient_name',
                'latest_record.age',
                'latest_record.sex',
                'patient_totals.bags',
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function demographicGroupDonors(
        string $year,
        string $month,
        string $location,
        string $type,
        string $group
    ): array {
        $query = $this->donorSummaryQuery($year, $month, $location);

        if ($type === 'age') {
            $bracket = collect($this->ageBracketDefinitions())->firstWhere('group', $group);
            abort_if($bracket === null, 404);
            $query->whereBetween('donors.age', [$bracket['min'], $bracket['max']]);
        } elseif ($type === 'gender' && in_array($group, ['Female', 'Male'], true)) {
            $query->whereRaw('LOWER(donors.gender) = ?', [mb_strtolower($group)]);
        } else {
            abort(404);
        }

        return $this->summarizeDonors($query)->all();
    }

    private function ageReportFromDonors(Collection $rows): array
    {
        $total = $rows->count();
        $groups = [];

        foreach ($this->ageBracketDefinitions() as $bracket) {
            $list = $rows
                ->filter(fn (array $donor) => $donor['age'] >= $bracket['min'] && $donor['age'] <= $bracket['max'])
                ->values()
                ->all();
            $groups[] = [
                'group' => $bracket['group'],
                'donors' => $list,
                'totalDonations' => (int) array_sum(array_column($list, 'bags')),
                'percentage' => $total ? (int) round((count($list) / $total) * 100) : 0,
            ];
        }

        return [
            'totalDonors' => $total,
            'groups' => $groups,
        ];
    }

    public function gender(int $year, string $month, string $location = ''): array
    {
        $donors = $this->donorSummary((string) $year, $month, $location);

        return [
            'donors' => $donors->all(),
            'typeBags' => $this->genderTypeBags($year, $month, $location),
        ];
    }

    private function genderTypeBags(int $year, string $month, string $location): array
    {
        $typeColumn = "COALESCE(NULLIF(donations.blood_type, ''), donors.blood_type)";
        $typeBags = [];

        $this->donorSummaryQuery((string) $year, $month, $location)
            ->groupBy('donors.gender', DB::raw($typeColumn))
            ->select('donors.gender')
            ->selectRaw($typeColumn.' as report_blood_type')
            ->selectRaw('SUM(CASE WHEN donations.bags IS NULL OR donations.bags = 0 THEN 1 ELSE donations.bags END) as total_bags')
            ->get()
            ->each(function ($row) use (&$typeBags) {
                $gender = (string) $row->gender;
                $type = (string) $row->report_blood_type;
                $typeBags[$gender][$type] = (int) $row->total_bags;
            });

        return $typeBags;
    }

    public function consumption(
        string $location = '',
        string $bloodType = '',
        string $month = '',
        string $year = '',
        bool $includeRecords = true
    ): array {
        $query = $this->consumptionRecordsQuery($location, $bloodType, $month, $year)
            ->select($includeRecords
                ? ['date_submitted', 'patient_name', 'age', 'sex', 'blood_component', 'blood_type', 'number_of_bags', 'location', 'province', 'municipality']
                : ['blood_type', 'number_of_bags', 'location', 'province', 'municipality'])
            ->when($includeRecords, fn ($query) => $query->orderBy('date_submitted')->orderByDesc('id'));

        $totalUnits = 0;
        $btMap = [];
        $provinceGroups = [];
        foreach ($query->cursor() as $record) {
            $units = (int) $record->number_of_bags;
            $totalUnits += $units;
            $bloodType = trim((string) $record->blood_type);
            if ($bloodType !== '') {
                $btMap[$bloodType] = ($btMap[$bloodType] ?? 0) + $units;
            }

            $location = BloodBank::resolveCaragaLocation($record->location, $record->municipality);
            $province = trim((string) $record->province);
            $municipality = trim((string) $record->municipality);
            $province = BloodBank::officialProvinceName($province !== '' ? $province : $location['province']);
            $municipality = $municipality !== '' ? $municipality : $location['municipality'];

            if (! isset($provinceGroups[$province])) {
                $provinceGroups[$province] = [
                    'name' => $province,
                    'total_units' => 0,
                    'patient_count' => 0,
                    'municipalities' => [],
                ];
            }
            if (! isset($provinceGroups[$province]['municipalities'][$municipality])) {
                $provinceGroups[$province]['municipalities'][$municipality] = [
                    'name' => $municipality,
                    'total_units' => 0,
                    'patient_count' => 0,
                    'records' => [],
                ];
            }

            $provinceGroups[$province]['total_units'] += $units;
            $provinceGroups[$province]['patient_count']++;
            $provinceGroups[$province]['municipalities'][$municipality]['total_units'] += $units;
            $provinceGroups[$province]['municipalities'][$municipality]['patient_count']++;

            if ($includeRecords) {
                $provinceGroups[$province]['municipalities'][$municipality]['records'][] = [
                    'date_submitted' => $record->date_submitted,
                    'patient_name' => trim((string) $record->patient_name),
                    'age' => $record->age,
                    'sex' => $record->sex,
                    'component' => $record->blood_component,
                    'units' => $units,
                ];
            }
        }

        $top = null;
        $max = 0;
        $byBloodType = [];
        foreach ($btMap as $type => $total) {
            $byBloodType[] = ['type' => $type, 'total' => $total];
            if ($total > $max) {
                $max = $total;
                $top = $type;
            }
        }

        ksort($provinceGroups, SORT_NATURAL | SORT_FLAG_CASE);
        $provinces = collect(array_values(array_map(function (array $province): array {
            ksort($province['municipalities'], SORT_NATURAL | SORT_FLAG_CASE);
            $municipalities = array_values($province['municipalities']);

            return [
                ...$province,
                'municipality_count' => count($municipalities),
                'municipalities' => $municipalities,
            ];
        }, $provinceGroups)));

        return [
            'provinces' => $provinces,
            'totalUnits' => $totalUnits,
            'provinceCount' => $provinces->count(),
            'topBloodType' => $top,
            'byBloodType' => $byBloodType,
        ];
    }

    private function consumptionRecordsQuery(
        string $location,
        string $bloodType,
        string $month,
        string $year
    ): Builder {
        return ConsumptionRecord::query()
            ->when($location !== '' && strcasecmp($location, 'all') !== 0, function ($query) use ($location) {
                $needle = '%'.addcslashes(mb_strtolower(trim($location)), '%_\\').'%';
                $query->where(function ($query) use ($needle) {
                    $query->whereRaw('LOWER(location) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(province) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(municipality) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(barangay) LIKE ?', [$needle]);
                });
            })
            ->when($bloodType !== '' && strcasecmp($bloodType, 'all') !== 0, function ($query) use ($bloodType) {
                $query->whereRaw('LOWER(blood_type) = ?', [mb_strtolower(trim($bloodType))]);
            })
            ->when($month !== '' && strcasecmp($month, 'all') !== 0, function ($query) use ($month) {
                $query->where('month', (int) $month);
            })
            ->when($year !== '' && strcasecmp($year, 'all') !== 0 && ctype_digit((string) $year), function ($query) use ($year) {
                $query->where('year', (int) $year);
            });
    }

    /**
     * @return array{
     *     province: string,
     *     municipality: string,
     *     records: array<int, array{date: string, patient_name: string, age: int|string, sex: string, component: string, units: int}>
     * }
     */
    public function consumptionMunicipalityRecords(
        string $province,
        string $municipality,
        string $location = '',
        string $bloodType = ''
    ): array {
        $municipalityLike = '%'.addcslashes(mb_strtolower(trim($municipality)), '%_\\').'%';
        $records = ConsumptionRecord::query()
            ->where(function ($query) use ($municipality, $municipalityLike) {
                $query->where('municipality', $municipality)
                    ->orWhere(function ($legacy) use ($municipalityLike) {
                        $legacy->where(function ($empty) {
                            $empty->whereNull('municipality')->orWhere('municipality', '');
                        })->whereRaw('LOWER(location) LIKE ?', [$municipalityLike]);
                    });
            })
            ->when($location !== '' && strcasecmp($location, 'all') !== 0, function ($query) use ($location) {
                $needle = '%'.addcslashes(mb_strtolower(trim($location)), '%_\\').'%';
                $query->where(function ($query) use ($needle) {
                    $query->whereRaw('LOWER(location) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(province) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(municipality) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(barangay) LIKE ?', [$needle]);
                });
            })
            ->when($bloodType !== '' && strcasecmp($bloodType, 'all') !== 0, function ($query) use ($bloodType) {
                $query->whereRaw('LOWER(blood_type) = ?', [mb_strtolower(trim($bloodType))]);
            })
            ->select([
                'location',
                'province',
                'municipality',
                'patient_name',
                'age',
                'sex',
                'blood_component',
                'number_of_bags',
                'date_submitted',
            ])
            ->orderBy('date_submitted')
            ->orderBy('id')
            ->get()
            ->filter(function (ConsumptionRecord $record) use ($province, $municipality): bool {
                $resolved = BloodBank::resolveCaragaLocation($record->location, $record->municipality);
                $recordProvince = trim((string) $record->province) ?: $resolved['province'];
                $recordMunicipality = trim((string) $record->municipality) ?: $resolved['municipality'];

                return strcasecmp($recordProvince, $province) === 0
                    && strcasecmp($recordMunicipality, $municipality) === 0;
            })
            ->map(fn (ConsumptionRecord $record): array => [
                'date' => BloodBank::formatDate($record->date_submitted),
                'patient_name' => trim((string) $record->patient_name),
                'age' => $record->age ?? '—',
                'sex' => $record->sex ?: '—',
                'component' => $record->blood_component ?: '—',
                'units' => (int) $record->number_of_bags,
            ])
            ->values()
            ->all();

        return [
            'province' => $province,
            'municipality' => $municipality,
            'records' => $records,
        ];
    }

    private function donorSummary(string $year, string $month, string $location): Collection
    {
        return $this->summarizeDonors($this->donorSummaryQuery($year, $month, $location));
    }

    private function summarizeDonors(\Illuminate\Database\Query\Builder $query): Collection
    {
        return $query
            ->groupBy(
                'donors.id',
                'donors.full_name',
                'donors.first_name',
                'donors.middle_name',
                'donors.last_name',
                'donors.suffix',
                'donors.blood_type',
                'donors.age',
                'donors.gender',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            )
            ->select([
                'donors.full_name',
                'donors.first_name',
                'donors.middle_name',
                'donors.last_name',
                'donors.suffix',
                'donors.blood_type',
                'donors.age',
                'donors.gender',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            ])
            ->selectRaw('SUM(CASE WHEN donations.bags IS NULL OR donations.bags = 0 THEN 1 ELSE donations.bags END) as total_bags')
            ->selectRaw('MAX(donations.donated_on) as last_donation')
            ->orderBy('donors.full_name')
            ->get()
            ->map(function ($row) {
                $composed = Donor::composeName($row->first_name, $row->middle_name, $row->last_name, $row->suffix);

                return [
                    'name' => $composed !== '' ? $composed : (string) $row->full_name,
                    'blood_type' => $row->blood_type,
                    'age' => (int) $row->age,
                    'gender' => (string) $row->gender,
                    'contact' => $row->contact,
                    'address' => BloodBank::formatAssignedAddress($row->barangay, $row->municipality, $row->province),
                    'bags' => (int) $row->total_bags,
                    'last_donation' => $row->last_donation,
                    'last_donation_display' => BloodBank::formatDate($row->last_donation),
                ];
            });
    }

    private function donorSummaryQuery(string $year, string $month, string $location): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('donations')->join('donors', 'donors.id', '=', 'donations.donor_id');

        if ($year !== '' && strcasecmp($year, 'all') !== 0) {
            $query->whereYear('donations.donated_on', (int) $year);
        }
        if ($month !== '' && strcasecmp($month, 'all') !== 0) {
            $query->whereMonth('donations.donated_on', (int) $month);
        }

        $this->constrainDonorLocation($query, $location);

        return $query;
    }

    public function constrainDonorLocation(Builder|\Illuminate\Database\Query\Builder $query, string $location): void
    {
        $location = trim($location);
        if ($location === '') {
            return;
        }

        $origin = $this->originExpression();
        $like = '%'.addcslashes(mb_strtolower($location), '%_\\').'%';
        $municipalities = collect(BloodBank::MUNICIPALITY_PROVINCE_MAP)
            ->filter(fn (string $province) => str_contains(mb_strtolower($province), mb_strtolower($location)))
            ->keys()
            ->map(fn (string $municipality) => mb_strtolower($municipality))
            ->all();
        $query->where(function ($inner) use ($like, $origin, $municipalities) {
            $inner->whereRaw('LOWER(donors.barangay) like ?', [$like])
                ->orWhereRaw('LOWER(donors.municipality) like ?', [$like])
                ->orWhereRaw('LOWER(donors.province) like ?', [$like])
                ->orWhereRaw("LOWER({$origin}) like ?", [$like]);

            if ($municipalities !== []) {
                $placeholders = implode(', ', array_fill(0, count($municipalities), '?'));
                $inner->orWhereRaw("LOWER(donors.municipality) IN ({$placeholders})", $municipalities);
            }
        });
    }

    private function originExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "donors.barangay || ', ' || donors.municipality || CASE WHEN COALESCE(donors.province, '') = '' THEN '' ELSE ', ' || donors.province END";
        }

        return "CONCAT_WS(', ', NULLIF(donors.barangay, ''), NULLIF(donors.municipality, ''), NULLIF(donors.province, ''))";
    }

    private function countDistinctDonorIds(Builder $query): int
    {
        return $this->countDistinctIds($query, 'donations.donor_id');
    }

    private function countDistinctIds(Builder $query, string $column): int
    {
        $row = (clone $query)->reorder()->selectRaw("COUNT(DISTINCT {$column}) as aggregate")->first();

        return (int) ($row->aggregate ?? 0);
    }

    private function ageBracketDefinitions(): array
    {
        return [
            ['group' => '16–25', 'min' => 16, 'max' => 25],
            ['group' => '26–35', 'min' => 26, 'max' => 35],
            ['group' => '36–45', 'min' => 36, 'max' => 45],
            ['group' => '46–55', 'min' => 46, 'max' => 55],
            ['group' => '56+', 'min' => 56, 'max' => 120],
        ];
    }
}
