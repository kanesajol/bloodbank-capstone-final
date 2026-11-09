<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\BloodBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $period = (string) $request->query('period', 'monthly');
        if (! in_array($period, ['monthly', 'quarterly', 'yearly'], true)) {
            $period = 'monthly';
        }

        $view = (string) $request->query('view', '');
        if ($view === '') {
            $legacy = (string) $request->query('tab', 'periodic');
            if (! $request->exists('period') && in_array($legacy, ['monthly', 'quarterly', 'yearly'], true)) {
                $period = $legacy;
            }
            $view = match ($legacy) {
                'age', 'gender' => 'demographics',
                'consumption' => 'consumption',
                default => 'periodic',
            };
        }
        if (! in_array($view, ['periodic', 'demographics', 'consumption'], true)) {
            $view = 'periodic';
        }

        $month = $this->normalizeMonth($request->query('month', $request->query('gender_month')));
        $year = $this->normalizeYear(
            $request->query('year', $request->query('age_year', $request->query('gender_year', (string) now()->year))),
            (int) now()->year
        );
        $periodicYears = BloodBank::YEARS;
        $periodicYear = (int) $year;
        if (! in_array($periodicYear, $periodicYears, true)) {
            $periodicYear = in_array((int) now()->year, $periodicYears, true)
                ? (int) now()->year
                : max($periodicYears);
        }
        $reportLocation = $this->displayLocation(trim((string) $request->query('location', '')));
        $consumptionBlood = (string) $request->query('blood_type', '');
        $ageYear = (string) $year;
        $printAll = $request->boolean('print_all');

        $periodic = ['period' => $period, 'rows' => []];
        $printPeriods = null;
        $ageReport = ['totalDonors' => 0, 'groups' => []];
        $gender = ['total' => 0, 'rows' => [], 'donorsList' => collect()];
        $consumptionDemographics = [
            'age' => ['totalPatients' => 0, 'groups' => []],
            'gender' => ['totalPatients' => 0, 'rows' => []],
        ];
        $consumption = [
            'provinces' => collect(),
            'totalUnits' => 0,
            'provinceCount' => 0,
            'topBloodType' => null,
            'byBloodType' => [],
        ];

        if ($view === 'periodic' || $printAll) {
            $periodic = $this->periodicReport($period, $periodicYear, $reportLocation);
        }
        if ($printAll) {
            $printPeriods = $this->printPeriodSummaries($periodicYear, $reportLocation);
        }

        if ($view === 'demographics' || $printAll) {
            if ($printAll) {
                $ageReport = $this->presentAge(
                    $this->reports->ageReport($ageYear, $reportLocation, $month),
                    true
                );
                $gender = $this->presentGender($this->reports->gender((int) $year, $month, $reportLocation));
            } else {
                $demographics = $this->reports->demographics($ageYear, $month, $reportLocation);
                $ageReport = $this->presentAge($demographics['age']);
                $gender = $demographics['gender'];
            }
            $consumptionDemographics = $this->reports->consumptionDemographics(
                $month,
                (string) $year,
                $reportLocation,
            );
        }

        if ($view === 'consumption' || $printAll) {
            $consumption = $this->reports->consumption(
                $reportLocation,
                $printAll ? '' : $consumptionBlood,
                '',
                $printAll ? (string) $periodicYear : '',
                false,
            );
        }

        return view('reports', [
            'view' => $view,
            'printAll' => $printAll,
            'period' => $period,
            'months' => BloodBank::MONTHS,
            'years' => BloodBank::YEARS,
            'bloodTypes' => BloodBank::BLOOD_TYPES,
            'month' => $month,
            'monthLabel' => $month === 'all' ? 'All Months' : BloodBank::MONTHS[(int) $month - 1],
            'year' => $year,
            'periodicYear' => $periodicYear,
            'periodicYears' => $periodicYears,
            'reportLocation' => $reportLocation,
            'periodic' => $periodic,
            'printPeriods' => $printPeriods,
            'ageYear' => $ageYear,
            'ageReport' => $ageReport,
            'genderYear' => (int) $year,
            'genderMonth' => $month,
            'gender' => $gender,
            'consumptionDemographics' => $consumptionDemographics,
            'consumptionLocation' => $reportLocation,
            'consumptionBlood' => $consumptionBlood,
            'consumption' => $consumption,
        ]);
    }

    public function consumptionDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'blood_type' => ['nullable', 'string', 'max:8'],
        ]);

        return response()->json($this->reports->consumptionMunicipalityRecords(
            $validated['province'],
            $validated['municipality'],
            (string) ($validated['location'] ?? ''),
            (string) ($validated['blood_type'] ?? ''),
        ));
    }

    public function demographicDetails(Request $request): JsonResponse
    {
        $groups = $request->input('type') === 'age'
            ? ['16–25', '26–35', '36–45', '46–55', '56+']
            : ['Female', 'Male'];
        $validated = $request->validate([
            'type' => ['required', Rule::in(['age', 'gender'])],
            'group' => ['required', 'string', Rule::in($groups)],
            'month' => ['nullable', 'string'],
            'year' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $year = (string) $this->normalizeYear($validated['year'] ?? '', (int) now()->year);
        $month = $this->normalizeMonth($validated['month'] ?? 'all');
        $location = $this->displayLocation(trim((string) ($validated['location'] ?? '')));

        return response()->json([
            'type' => $validated['type'],
            'group' => $validated['group'],
            'donors' => $this->reports->demographicGroupDonors(
                $year,
                $month,
                $location,
                $validated['type'],
                $validated['group'],
            ),
        ]);
    }

    public function consumptionDemographicDetails(Request $request): JsonResponse
    {
        $groups = $request->input('type') === 'age'
            ? ['16–25', '26–35', '36–45', '46–55', '56+']
            : ['Female', 'Male'];
        $validated = $request->validate([
            'type' => ['required', Rule::in(['age', 'gender'])],
            'group' => ['required', 'string', Rule::in($groups)],
            'month' => ['nullable', 'string'],
            'year' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $year = (string) $this->normalizeYear($validated['year'] ?? '', (int) now()->year);
        $month = $this->normalizeMonth($validated['month'] ?? 'all');
        $location = $this->displayLocation(trim((string) ($validated['location'] ?? '')));

        return response()->json([
            'type' => $validated['type'],
            'group' => $validated['group'],
            'patients' => $this->reports->consumptionDemographicPatients(
                $month,
                $year,
                $location,
                $validated['type'],
                $validated['group'],
            ),
        ]);
    }

    private function normalizeMonth(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || strcasecmp($value, 'all') === 0) {
            return 'all';
        }

        $month = (int) $value;
        if ($month < 1 || $month > 12) {
            return 'all';
        }

        return (string) $month;
    }

    private function normalizeYear(mixed $value, int|string $whenAll): int|string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || strcasecmp($value, 'all') === 0 || ! ctype_digit($value)) {
            return $whenAll;
        }

        return is_int($whenAll) ? (int) $value : $value;
    }

    private function quarterlyReport(int|string $year, string $month, string $location): array
    {
        $quarterly = $this->reports->quarterly((int) $year, $location);
        if ($month === 'all') {
            return $quarterly;
        }

        $monthNumber = (int) $month;

        return array_map(function (array $row) use ($monthNumber) {
            $row['newDonors'] = $row['newDonors']
                ->filter(fn ($donor) => (int) $donor->registered_date->month === $monthNumber)
                ->values();

            return $row;
        }, $quarterly);
    }

    private function periodicReport(string $period, int $year, string $location): array
    {
        if ($period === 'yearly') {
            $rows = [];
            foreach (BloodBank::YEARS as $historicalYear) {
                $rows[] = $this->periodRow(
                    'year-'.$historicalYear,
                    (string) $historicalYear,
                    (string) $historicalYear,
                    sprintf('%d-01-01', $historicalYear),
                    sprintf('%d-12-31', $historicalYear),
                    $location
                );
            }

            return ['period' => 'yearly', 'rows' => $rows];
        }

        if ($period === 'quarterly') {
            $ranges = [
                ['q1-'.$year, 'Q1', 'January to March', $year.'-01-01', $year.'-03-31'],
                ['q2-'.$year, 'Q2', 'April to June', $year.'-04-01', $year.'-06-30'],
                ['q3-'.$year, 'Q3', 'July to September', $year.'-07-01', $year.'-09-30'],
                ['q4-'.$year, 'Q4', 'October to December', $year.'-10-01', $year.'-12-31'],
            ];
            $rows = [];
            foreach ($ranges as [$id, $label, $detail, $start, $end]) {
                $rows[] = $this->periodRow($id, $label, $detail, $start, $end, $location);
            }

            return ['period' => 'quarterly', 'rows' => $rows];
        }

        $rows = [];
        foreach (BloodBank::MONTHS as $index => $name) {
            $month = $index + 1;
            $start = Carbon::create($year, $month, 1);
            $rows[] = $this->periodRow(
                'month-'.$year.'-'.$month,
                $name,
                $name.' '.$year,
                $start->toDateString(),
                $start->copy()->endOfMonth()->toDateString(),
                $location
            );
        }

        return ['period' => 'monthly', 'rows' => $rows];
    }

    /**
     * @return array{monthly: array{period: string, rows: list<array<string, mixed>>}, quarterly: array{period: string, rows: list<array<string, mixed>>}, yearly: array{period: string, rows: list<array<string, mixed>>}}
     */
    private function printPeriodSummaries(int $year, string $location): array
    {
        $withoutRecords = function (array $report): array {
            $report['rows'] = array_map(static function (array $row): array {
                unset($row['records']);

                return $row;
            }, $report['rows']);

            return $report;
        };

        return [
            'monthly' => $withoutRecords($this->periodicReport('monthly', $year, $location)),
            'quarterly' => $withoutRecords($this->periodicReport('quarterly', $year, $location)),
            'yearly' => $withoutRecords([
                'period' => 'yearly',
                'rows' => [
                    $this->periodRow(
                        'year-'.$year,
                        (string) $year,
                        (string) $year,
                        sprintf('%d-01-01', $year),
                        sprintf('%d-12-31', $year),
                        $location,
                    ),
                ],
            ]),
        ];
    }

    private function periodRow(string $id, string $label, string $detail, string $start, string $end, string $location): array
    {
        $records = $this->periodDonors($start, $end, $location);

        return [
            'id' => $id,
            'label' => $label,
            'detail' => $detail,
            'donors' => count($records),
            'bags' => (int) array_sum(array_column($records, 'bags')),
            'records' => $records,
        ];
    }

    private function periodDonors(string $start, string $end, string $location): array
    {
        $query = DB::table('donations')
            ->join('donors', 'donors.id', '=', 'donations.donor_id')
            ->whereDate('donations.donated_on', '>=', $start)
            ->whereDate('donations.donated_on', '<=', $end);

        $this->reports->constrainDonorLocation($query, $location);

        return $query
            ->groupBy(
                'donors.id',
                'donors.full_name',
                'donors.blood_type',
                'donors.age',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            )
            ->select([
                'donors.id',
                'donors.full_name',
                'donors.blood_type',
                'donors.age',
                'donors.contact',
                'donors.barangay',
                'donors.municipality',
                'donors.province',
            ])
            ->selectRaw('SUM(CASE WHEN donations.bags IS NULL OR donations.bags = 0 THEN 1 ELSE donations.bags END) as total_period_bags')
            ->selectRaw('MAX(donations.donated_on) as latest_collection_date')
            ->orderBy('donors.full_name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->full_name,
                'blood_type' => $row->blood_type,
                'age' => (int) $row->age,
                'contact' => $row->contact,
                'address' => BloodBank::formatAssignedAddress($row->barangay, $row->municipality, $row->province),
                'date' => $row->latest_collection_date,
                'bags' => (int) $row->total_period_bags,
            ])
            ->all();
    }

    private function displayLocation(string $selected): string
    {
        if ($selected === '' || strcasecmp($selected, 'all') === 0) {
            return '';
        }

        if (str_starts_with(mb_strtolower($selected), 'all locations')) {
            return '';
        }

        if (str_starts_with(mb_strtolower($selected), 'all ')) {
            return trim(substr($selected, 4));
        }

        return $selected;
    }

    private function filterGenderByAddress(array $gender, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return $gender;
        }

        $needle = mb_strtolower($search);
        $gender['donorsList'] = $gender['donorsList']
            ->filter(fn ($donor) => str_contains(mb_strtolower(BloodBank::formatAssignedAddress(
                $donor->barangay,
                $donor->municipality,
                $donor->province,
            )), $needle))
            ->values();

        return $gender;
    }

    private function presentAge(array $ageReport, bool $includeDetails = false): array
    {
        $groups = [];

        foreach ($ageReport['groups'] as $index => $group) {
            $donors = array_values($group['donors']);
            $count = (int) ($group['count'] ?? count($donors));

            $presentedGroup = [
                'group' => $group['group'],
                'count' => $count,
                'bags' => (int) $group['totalDonations'],
                'modalId' => 'age-donors-'.$index,
            ];
            if ($includeDetails) {
                $presentedGroup['donors'] = $donors;
            }

            $groups[] = $presentedGroup;
        }

        $total = (int) collect($groups)->sum('count');
        foreach ($groups as $index => $group) {
            $groups[$index]['percentage'] = $total > 0 ? (int) round(($group['count'] / $total) * 100) : 0;
        }

        return [
            'totalDonors' => $total,
            'groups' => $groups,
        ];
    }

    private function presentGender(array $gender): array
    {
        $donors = collect($gender['donors']);
        $total = $donors->count();
        $rows = [];

        foreach (['Female', 'Male'] as $genderName) {
            $group = $donors
                ->filter(fn (array $donor) => strcasecmp($donor['gender'], $genderName) === 0)
                ->values();
            $typeBags = [];
            foreach ($gender['typeBags'] as $name => $bagsByType) {
                if (strcasecmp((string) $name, $genderName) === 0) {
                    $typeBags = $bagsByType;
                    break;
                }
            }
            arsort($typeBags);
            $count = $group->count();
            $rows[] = [
                'gender' => $genderName,
                'count' => $count,
                'percentage' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                'bags' => (int) $group->sum('bags'),
                'topBloodType' => $typeBags === [] ? '—' : (string) array_key_first($typeBags),
                'donors' => $group,
            ];
        }

        return [
            'total' => $total,
            'rows' => $rows,
        ];
    }

    private function presentYearly(array $yearly): array
    {
        $totalBags = (int) collect($yearly['byBloodType'])->sum('bags');
        $yearly['byBloodType'] = array_map(function (array $row) use ($totalBags) {
            $bags = (int) $row['bags'];
            $row['bags'] = $bags;
            $row['percentage'] = $totalBags > 0 ? (int) round(($bags / $totalBags) * 100) : 0;

            return $row;
        }, $yearly['byBloodType']);

        return $yearly;
    }
}
