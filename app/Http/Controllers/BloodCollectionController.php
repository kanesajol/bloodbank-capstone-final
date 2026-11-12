<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithDonorSearch;
use App\Models\Donation;
use App\Models\Donor;
use App\Support\BloodBank;
use App\Support\DonationScreening;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BloodCollectionController extends Controller
{
    use InteractsWithDonorSearch;

    public function index(Request $request): View|JsonResponse
    {
        if ($request->query('lookup') === 'returning') {
            $term = $request->query('q', '');

            return response()->json($this->returningMatches(is_string($term) ? $term : ''));
        }

        $mode = $request->query('mode', 'name');
        if (! in_array($mode, ['name', 'location-year'], true)) {
            $mode = 'name';
        }

        $searchTerm = $request->query('q', '');
        $searchTerm = is_string($searchTerm) ? trim($searchTerm) : '';

        [$donors, $donorTotal] = $mode === 'name'
            ? $this->searchDonors($searchTerm)
            : [collect(), 0];

        $selected = null;
        if ($request->filled('donor')) {
            $donorId = (int) $request->query('donor');
            $selected = $donorId > 0 ? Donor::with('donations')->find($donorId) : null;
        }

        $year = (int) $request->query('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }
        $location = $request->query('location', $request->query('municipality', ''));
        $location = is_string($location) ? trim($location) : '';
        $years = [now()->year - 2, now()->year - 1, now()->year, now()->year + 1];

        $returningDonor = null;
        if (old('form') === 'returning-donation') {
            $returningId = (int) old('returning_donor');
            $returningDonor = $returningId > 0 ? Donor::withCount('donations')->find($returningId) : null;
        }

        $pageView = match (true) {
            old('form') === 'edit-donor' => 'edit-donor',
            in_array(old('form'), ['register-donor', 'returning-donation'], true) => 'register-donor',
            $selected !== null && $request->query('edit') === '1' => 'edit-donor',
            $selected !== null => 'donor',
            $request->query('register') === '1' => 'register-donor',
            default => null,
        };

        return view('blood-collection', [
            'mode' => $mode,
            'donors' => $donors,
            'donorTotal' => $donorTotal,
            'searchTerm' => $searchTerm,
            'returningDonor' => $returningDonor,
            'selectedDonor' => $selected,
            'showAddDonation' => $request->query('add') === '1' && $selected,
            'locationQuery' => $location,
            'year' => $year,
            'years' => $years,
            'monthGroups' => $mode === 'location-year'
                ? $this->donorsByAddress($location, $year)
                : [],
            'bloodTypes' => BloodBank::BLOOD_TYPES,
            'editingDonor' => $pageView === 'edit-donor',
            'openRegister' => $pageView === 'register-donor',
            'registerTab' => old('register_tab', $request->query('register_tab', 'new')),
            'pageView' => $pageView,
            'provinces' => BloodConsumptionController::PROVINCES,
            'locationChoices' => BloodConsumptionController::formLocationChoices(),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, blood_type: ?string, age: ?int, gender: ?string, address: string, donations: int}>
     */
    private function returningMatches(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 1) {
            return [];
        }

        $words = $this->nameSearchWords($term);
        $query = Donor::query()->withCount('donations');
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('LOWER(full_name) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(middle_name, \'\')) like ?', [$like]);
            });
        }
        $this->rankNameMatches($query, $words);

        return $query
            ->limit(20)
            ->get()
            ->map(fn (Donor $donor) => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
                'blood_type' => $donor->blood_type,
                'age' => $donor->age,
                'gender' => $donor->gender,
                'address' => $donor->formattedAddress(),
                'province' => BloodBank::officialProvinceName((string) ($donor->province ?: BloodBank::provinceForMunicipality($donor->municipality))),
                'municipality' => $donor->municipality,
                'barangay' => $donor->barangay,
                'donations' => (int) $donor->donations_count,
            ])
            ->all();
    }

    public function storeDonor(Request $request): RedirectResponse
    {
        if ($request->filled('donor_id')) {
            return $this->updateDonor($request);
        }

        $usingParts = $this->usesNameParts($request);
        $usingScreening = $request->exists('status');
        $this->mergeMbdDate($request);
        $request->merge($this->donorNameInput($request));
        $this->mergeTitleCaseNotes($request);

        $validated = $request->validate([
            ...$this->donorNameRules($usingParts),
            'blood_type' => ['required', Rule::in(BloodBank::BLOOD_TYPES)],
            'age' => ['required', 'integer', 'min:16', 'max:70'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'contact' => ['required', 'string', 'max:50'],
            'barangay' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            ...$this->screeningRules($request, $usingScreening),
        ]);

        if ($redirect = $this->alignAddress($validated)) {
            return $redirect;
        }

        if ($usingScreening) {
            $mbdDate = DonationScreening::parseMbdDate((string) $validated['mbd_date']);
            if ($mbdDate === null) {
                return back()
                    ->withErrors(['mbd_date' => 'Enter a valid date, such as 01/23/2025.'])
                    ->withInput();
            }
            $validated['initial_donation_date'] = $mbdDate;
            $validated['mbd_date'] = DonationScreening::composeMbdDate(
                (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom((string) $validated['mbd_date'])),
                $mbdDate,
            );
            $validated['donation_location'] = BloodBank::formatAssignedAddress(
                $validated['barangay'],
                $validated['municipality'],
                $validated['province'] ?? null,
            );
        }

        if (($validated['status'] ?? null) === 'deferred') {
            $validated['serial_number'] = null;
            $validated['reserved_for_px'] = null;
            if (($validated['reason_for_deferral'] ?? '') === 'Custom Reason') {
                $validated['reason_for_deferral'] = trim((string) ($validated['reason_for_deferral_custom'] ?? ''));
            }
        } elseif (($validated['status'] ?? null) === 'passed') {
            $validated['reason_for_deferral'] = null;
        }

        $result = Donor::recordProfileOrDonation($validated);
        $donor = $result['donor'];

        $message = $this->registrationMessage($donor, $result['attached'], $validated['status'] ?? null, $validated['serial_number'] ?? null);

        return redirect()
            ->route('blood-collection', [
                'mode' => 'name',
                'donor' => $donor->id,
            ])
            ->with('success', $message);
    }

    public function storeDonation(Request $request, Donor $donor): RedirectResponse
    {
        $this->mergeMbdDate($request);

        $validated = $request->validate([
            'mbd_date' => ['required', 'string', 'max:100'],
        ]);

        $parsed = DonationScreening::parseMbdDate((string) $validated['mbd_date']);
        if ($parsed === null) {
            return back()
                ->withErrors(['mbd_date' => 'Enter a valid date, such as 01/23/2025.'])
                ->withInput();
        }

        $donor->donations()->create([
            'donated_on' => $parsed,
            'blood_type' => $donor->blood_type,
            'location' => DonationScreening::mbdTypeFrom((string) ($request->input('mbd_type') ?: $validated['mbd_date'])),
            'bags' => 1,
            'mbd_date' => DonationScreening::composeMbdDate(
                (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom((string) $validated['mbd_date'])),
                $parsed,
            ),
        ]);

        return redirect()
            ->route('blood-collection', [
                'mode' => $request->input('mode', 'name'),
                'donor' => $donor->id,
            ])
            ->with('success', "Donation for {$donor->full_name} was saved.");
    }

    public function destroyAllDonors(): RedirectResponse
    {
        $count = Donor::query()->count();
        Donor::query()->delete();

        return redirect()
            ->route('blood-collection', ['mode' => 'name'])
            ->with('success', $count === 1 ? '1 donor was deleted.' : "{$count} donors were deleted.");
    }

    public function destroyDonation(Request $request, Donation $donation): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['name', 'location', 'location-year'])],
            'return_location' => ['nullable', 'string', 'max:255'],
            'return_year' => ['nullable', 'integer'],
        ]);

        $donorId = $donation->donor_id;
        $donation->delete();

        return redirect()
            ->route('blood-collection', array_filter([
                'mode' => $validated['mode'] ?? 'name',
                'donor' => $donorId,
                'location' => $validated['return_location'] ?? null,
                'year' => $validated['return_year'] ?? null,
            ]))
            ->with('success', 'The donation record was deleted.');
    }

    private function updateDonor(Request $request): RedirectResponse
    {
        $donor = Donor::query()->findOrFail($request->input('donor_id'));
        $this->mergeMbdDate($request);
        $request->merge($this->donorNameInput($request));
        $this->mergeTitleCaseNotes($request);
        $usingParts = $this->usesNameParts($request);
        $nameInput = $this->donorNameInput($request);
        $name = $nameInput['full_name'];
        $request->merge($nameInput);

        $donation = $this->donationBeingEdited($donor, $request);

        $validated = $request->validate([
            ...$this->donorNameRules($usingParts),
            'blood_type' => ['required', 'string', 'max:8'],
            'age' => ['nullable', 'integer', 'min:16', 'max:70'],
            'gender' => ['required', Rule::in(array_values(array_unique(array_filter(['Male', 'Female', $donor->gender]))))],
            'contact' => ['nullable', 'string', 'max:50'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'mbd_date' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['passed', 'deferred'])],
            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('donations', 'serial_number')->ignore($donation?->id),
                Rule::requiredIf(fn () => $request->input('status') === 'passed'),
            ],
            'reserved_for_px' => ['nullable', 'string', 'max:255'],
            'reason_for_deferral' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred'),
                Rule::in(['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason']),
            ],
            'reason_for_deferral_custom' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred' && $request->input('reason_for_deferral') === 'Custom Reason'),
            ],
        ]);

        $mbdDate = trim((string) ($validated['mbd_date'] ?? ''));
        if ($mbdDate !== '') {
            $parsedMbdDate = DonationScreening::parseMbdDate($mbdDate);
            if ($parsedMbdDate === null) {
                return back()
                    ->withErrors(['mbd_date' => 'Enter a valid date, such as 01/23/2025.'])
                    ->withInput();
            }
            $validated['mbd_date'] = DonationScreening::composeMbdDate(
                (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom($mbdDate)),
                $parsedMbdDate,
            );
        } else {
            $validated['mbd_date'] = null;
        }

        if ($redirect = $this->alignAddress($validated)) {
            return $redirect;
        }

        $duplicate = Donor::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($name)])
            ->whereKeyNot($donor->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors(['full_name' => 'A donor with this name already exists.'])
                ->withInput();
        }

        try {
            $changes = [
                'full_name' => $validated['full_name'],
                'blood_type' => $validated['blood_type'],
                'age' => $validated['age'] ?? null,
                'gender' => $validated['gender'],
                'contact' => trim((string) ($validated['contact'] ?? '')),
                'barangay' => trim((string) ($validated['barangay'] ?? '')),
                'municipality' => trim((string) ($validated['municipality'] ?? '')),
                'province' => trim((string) ($validated['province'] ?? ''))
                    ?: BloodBank::provinceForMunicipality((string) ($validated['municipality'] ?? '')),
                'birthdate' => $validated['birthdate'] ?? null,
            ];

            if ($usingParts) {
                $changes['first_name'] = Donor::storedNamePart($validated['first_name'] ?? null);
                $changes['middle_name'] = Donor::storedNamePart($validated['middle_name'] ?? null);
                $changes['last_name'] = Donor::storedNamePart($validated['last_name'] ?? null);
                $changes['suffix'] = Donor::storedNamePart($validated['suffix'] ?? null);
            }

            DB::transaction(function () use ($donor, $donation, $changes, $validated) {
                $donor->update($changes);
                $this->saveDonationOutcome($donor, $donation, $validated);
            });
        } catch (UniqueConstraintViolationException) {
            return back()
                ->withErrors(['full_name' => 'A donor with this name already exists.'])
                ->withInput();
        }

        return redirect()
            ->route('blood-collection', [
                'mode' => 'name',
            ])
            ->with('success', 'Donor '.DonorController::displayNameFor($donor).' was updated.');
    }

    private function donationBeingEdited(Donor $donor, Request $request): ?Donation
    {
        $donationId = (int) $request->input('donation_id');
        if ($donationId > 0) {
            $donation = $donor->donations()->whereKey($donationId)->first();
            if ($donation !== null) {
                return $donation;
            }
        }

        return $donor->donations()->orderByDesc('donated_on')->orderByDesc('id')->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveDonationOutcome(Donor $donor, ?Donation $donation, array $validated): void
    {
        $status = $validated['status'] ?? null;
        if (! in_array($status, ['passed', 'deferred'], true)) {
            return;
        }

        $mbd = trim((string) ($validated['mbd_date'] ?? ''));
        $reason = null;
        if ($status === 'deferred') {
            $reason = ($validated['reason_for_deferral'] ?? '') === 'Custom Reason'
                ? trim((string) ($validated['reason_for_deferral_custom'] ?? ''))
                : trim((string) ($validated['reason_for_deferral'] ?? ''));
            $reason = $reason !== '' ? $reason : null;
        }

        $changes = [
            'mbd_date' => $mbd !== '' ? $mbd : null,
            'status' => $status,
            'blood_type' => $donor->blood_type,
            'serial_number' => $status === 'passed' ? trim((string) ($validated['serial_number'] ?? '')) : null,
            'reserved_for_px' => $status === 'passed' ? (trim((string) ($validated['reserved_for_px'] ?? '')) ?: null) : null,
            'reason_for_deferral' => $reason,
            'bags' => $status === 'deferred' ? 0 : max(1, (int) ($donation?->bags ?? 1)),
        ];

        if ($mbd !== '') {
            $parsed = DonationScreening::parseMbdDate($mbd);
            if ($parsed !== null) {
                $changes['donated_on'] = $parsed;
            }
        }

        if ($donation === null) {
            $donor->donations()->create([
                'donated_on' => $changes['donated_on'] ?? now()->toDateString(),
                'location' => $donor->formattedAddress(),
                ...$changes,
            ]);

            return;
        }

        $donation->update($changes);
    }

    /**
     * @return array<string, mixed>
     */
    private function screeningRules(Request $request, bool $usingScreening): array
    {
        if (! $usingScreening) {
            return [
                'initial_donation_date' => ['required', 'date'],
                'donation_location' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'mbd_date' => ['required', 'string', 'max:100'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['passed', 'deferred'])],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $request->input('status') === 'passed')],
            'reserved_for_px' => ['nullable', 'string', 'max:255'],
            'reason_for_deferral' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred'),
                Rule::in(['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason']),
            ],
            'reason_for_deferral_custom' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred' && $request->input('reason_for_deferral') === 'Custom Reason'),
            ],
        ];
    }

    private function registrationMessage(Donor $donor, bool $attached, ?string $status, ?string $serial): string
    {
        $name = DonorController::displayNameFor($donor);

        if ($status === 'deferred') {
            return $attached
                ? "Deferral recorded for {$name}. Their permanent address was kept."
                : "Donor {$name} was recorded as deferred.";
        }

        if ($status === 'passed') {
            $serialLabel = trim((string) $serial);

            return $attached
                ? "Passed collection attached to {$name}. Serial # {$serialLabel}. Their permanent address was kept."
                : "Donor {$name} passed and was added to the collection. Serial # {$serialLabel}.";
        }

        return $attached
            ? "Donation attached to {$name}. Their permanent address was kept."
            : "Donor {$name} was registered.";
    }

    private function usesNameParts(Request $request): bool
    {
        return $request->exists('first_name') || $request->exists('last_name') || $request->exists('middle_name') || $request->exists('suffix');
    }

    /**
     * @return array{full_name: string, first_name: ?string, middle_name: ?string, last_name: ?string, suffix: ?string}
     */
    private function donorNameInput(Request $request): array
    {
        if (! $this->usesNameParts($request)) {
            $full = preg_replace('/\s+/', ' ', trim((string) $request->input('full_name'))) ?? '';

            return [
                'full_name' => BloodBank::titleCaseText($full),
                'first_name' => null,
                'middle_name' => null,
                'last_name' => null,
                'suffix' => null,
            ];
        }

        $suffix = trim((string) $request->input('suffix', ''));
        if (strcasecmp($suffix, 'None') === 0) {
            $suffix = '';
        }

        $first = BloodBank::titleCaseText(trim((string) $request->input('first_name')));
        $middle = BloodBank::titleCaseText(trim((string) $request->input('middle_name')));
        $last = BloodBank::titleCaseText(trim((string) $request->input('last_name')));
        $rest = trim(implode(' ', array_filter([$first, $middle, $suffix], fn (string $part): bool => $part !== '')));
        $fullName = $last === '' ? $rest : ($rest === '' ? $last : $last.', '.$rest);

        return [
            'full_name' => $fullName,
            'first_name' => $first,
            'middle_name' => $middle !== '' ? $middle : null,
            'last_name' => $last,
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }

    private function mergeMbdDate(Request $request): void
    {
        if (! $request->filled('mbd_on')) {
            return;
        }

        $iso = DonationScreening::parseMbdDate((string) $request->input('mbd_on'));
        if ($iso === null) {
            return;
        }

        $request->merge([
            'mbd_date' => DonationScreening::composeMbdDate((string) $request->input('mbd_type', 'MBD'), $iso),
        ]);
    }

    private function mergeTitleCaseNotes(Request $request): void
    {
        $notes = [];
        foreach (['reserved_for_px', 'reason_for_deferral_custom'] as $field) {
            if ($request->filled($field)) {
                $notes[$field] = BloodBank::titleCaseText((string) $request->input($field));
            }
        }

        if ($notes !== []) {
            $request->merge($notes);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function alignAddress(array &$validated): ?RedirectResponse
    {
        $municipality = trim((string) ($validated['municipality'] ?? ''));
        $province = BloodBank::officialProvinceName(trim((string) ($validated['province'] ?? '')));
        if ($province !== '') {
            $validated['province'] = $province;
        }

        $mapped = $municipality === '' ? null : BloodBank::provinceForMunicipality($municipality);
        if ($mapped !== null && $province !== '' && strcasecmp($mapped, $province) !== 0) {
            return back()
                ->withErrors(['municipality' => 'Choose a municipality in the selected province.'])
                ->withInput();
        }

        $barangay = trim((string) ($validated['barangay'] ?? ''));
        $known = $municipality === '' ? [] : (BloodConsumptionController::places()[$municipality] ?? []);
        if ($barangay !== '' && $known !== [] && ! in_array($barangay, $known, true)) {
            return back()
                ->withErrors(['barangay' => 'Choose a barangay in the selected municipality.'])
                ->withInput();
        }

        if ($barangay !== '' && $known === []) {
            $validated['barangay'] = BloodBank::titleCaseText($barangay);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function donorNameRules(bool $usingParts): array
    {
        if (! $usingParts) {
            return [
                'full_name' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV'])],
            'full_name' => ['required', 'string', 'max:255'],
        ];
    }

    private function donorsByAddress(string $location, int $year): array
    {
        $query = DB::table('donations')
            ->join('donors', 'donors.id', '=', 'donations.donor_id')
            ->whereYear('donations.donated_on', $year)
            ->where(function ($status) {
                $status->whereNull('donations.status')->orWhere('donations.status', '!=', 'deferred');
            });

        $location = trim($location);
        if ($location !== '') {
            $like = '%'.addcslashes(mb_strtolower($location), '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('LOWER(donors.barangay) like ?', [$like])
                    ->orWhereRaw('LOWER(donors.municipality) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(donors.province, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(donations.location) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(donations.mbd_date, \'\')) like ?', [$like]);
            });
        }

        $rows = $query->orderByDesc('donations.donated_on')->limit(2000)->get([
            'donations.donor_id',
            'donations.donated_on',
            'donations.mbd_date',
            'donations.blood_type as donation_blood_type',
            'donors.full_name',
            'donors.last_name',
            'donors.first_name',
            'donors.middle_name',
            'donors.suffix',
            'donors.blood_type',
            'donors.barangay',
            'donors.municipality',
            'donors.province',
        ]);

        $byDonor = [];
        foreach ($rows as $row) {
            $id = (int) $row->donor_id;
            $date = Carbon::parse($row->donated_on);
            if (! isset($byDonor[$id]) || $date->greaterThan($byDonor[$id]['date'])) {
                $byDonor[$id]['date'] = $date;
                $byDonor[$id]['row'] = $row;
            }
            $byDonor[$id]['count'] = ($byDonor[$id]['count'] ?? 0) + 1;
        }

        if (count($byDonor) > 100) {
            uasort($byDonor, fn (array $a, array $b) => $b['date'] <=> $a['date']);
            $byDonor = array_slice($byDonor, 0, 100, true);
        }

        $buckets = [];
        foreach ($byDonor as $id => $item) {
            $row = $item['row'];
            $month = (int) $item['date']->month;
            $buckets[$month][] = [
                'id' => $id,
                'name' => DonorController::displayName(
                    $row->last_name,
                    $row->first_name,
                    $row->middle_name,
                    $row->suffix,
                    $row->full_name,
                ),
                'blood_type' => $row->blood_type ?: $row->donation_blood_type,
                'address' => BloodBank::formatAddress($row->barangay, $row->municipality, $row->province),
                'venue' => trim((string) ($row->mbd_date ?? '')),
                'count' => $item['count'],
                'date' => $item['date'],
            ];
        }

        ksort($buckets);
        $groups = [];
        foreach ($buckets as $month => $records) {
            usort($records, function (array $a, array $b) {
                $byDate = $b['date'] <=> $a['date'];

                return $byDate !== 0 ? $byDate : strcasecmp($a['name'], $b['name']);
            });

            $groups[] = [
                'month' => BloodBank::MONTHS[$month - 1],
                'records' => $records,
            ];
        }

        return $groups;
    }
}
