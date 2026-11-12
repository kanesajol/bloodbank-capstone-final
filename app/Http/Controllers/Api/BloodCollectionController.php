<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BloodConsumptionController;
use App\Http\Controllers\Concerns\InteractsWithDonorSearch;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DonorController;
use App\Models\Donation;
use App\Models\Donor;
use App\Support\BloodBank;
use App\Support\DonationScreening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BloodCollectionController extends Controller
{
    use InteractsWithDonorSearch;

    public function meta(): JsonResponse
    {
        $choices = BloodConsumptionController::formLocationChoices();

        return response()->json([
            'bloodTypes' => BloodBank::BLOOD_TYPES,
            'suffixes' => ['Jr.', 'Sr.', 'II', 'III', 'IV'],
            'deferralReasons' => ['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason'],
            'provinces' => BloodConsumptionController::PROVINCES,
            'municipalitiesByProvince' => $choices['municipalitiesByProvince'],
            'places' => $choices['places'],
        ]);
    }

    public function returning(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 1) {
            return response()->json(['donors' => []]);
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

        $donors = $query
            ->limit(20)
            ->get()
            ->map(fn (Donor $donor) => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
                'blood_type' => $donor->blood_type,
                'province' => BloodBank::officialProvinceName((string) ($donor->province ?: BloodBank::provinceForMunicipality($donor->municipality))),
                'municipality' => $donor->municipality,
                'barangay' => $donor->barangay,
                'donations' => (int) $donor->donations_count,
            ])
            ->values();

        return response()->json(['donors' => $donors]);
    }

    public function storeDonor(Request $request): JsonResponse
    {
        $usesNameParts = $request->exists('first_name')
            || $request->exists('last_name')
            || $request->exists('middle_name')
            || $request->exists('suffix');
        $usingScreening = $request->exists('status');
        $this->mergeMbdDate($request);

        $validated = $request->validate([
            'full_name' => [$usesNameParts ? 'nullable' : 'required', 'string', 'max:255'],
            'first_name' => [$usesNameParts ? 'required' : 'nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [$usesNameParts ? 'required' : 'nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV'])],
            'blood_type' => ['required', Rule::in(BloodBank::BLOOD_TYPES)],
            'age' => ['required', 'integer', 'min:16', 'max:70'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'contact' => ['required', 'string', 'max:50'],
            'barangay' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'initial_donation_date' => [$usingScreening ? 'nullable' : 'required', 'date'],
            'donation_location' => [$usingScreening ? 'nullable' : 'required', 'string', 'max:255'],
            'mbd_date' => [$usingScreening ? 'required' : 'nullable', 'string', 'max:100'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => [$usingScreening ? 'required' : 'nullable', Rule::in(['passed', 'deferred'])],
            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($usingScreening && $request->input('status') === 'passed'),
            ],
            'reserved_for_px' => ['nullable', 'string', 'max:255'],
            'reason_for_deferral' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($usingScreening && $request->input('status') === 'deferred'),
                Rule::in(['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason']),
            ],
            'reason_for_deferral_custom' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(
                    $usingScreening
                    && $request->input('status') === 'deferred'
                    && $request->input('reason_for_deferral') === 'Custom Reason'
                ),
            ],
        ]);

        if ($usesNameParts) {
            $validated['full_name'] = DonorController::displayName(
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['suffix'] ?? null,
            );
        }

        if ($usingScreening) {
            $mbdDate = DonationScreening::parseMbdDate($validated['mbd_date']);
            if ($mbdDate === null) {
                throw ValidationException::withMessages([
                    'mbd_date' => 'Enter a venue and date, such as WI 1/01/2025.',
                ]);
            }

            $validated['initial_donation_date'] = $mbdDate;
            $validated['donation_location'] = BloodBank::formatAssignedAddress(
                $validated['barangay'],
                $validated['municipality'],
                $validated['province'] ?? null,
            );
            $validated['mbd_date'] = DonationScreening::composeMbdDate(
                (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom((string) $validated['mbd_date'])),
                $mbdDate,
            );
            $validated['serial_number'] = $validated['status'] === 'passed'
                ? $validated['serial_number']
                : null;
            $validated['reserved_for_px'] = $validated['status'] === 'passed'
                ? ($validated['reserved_for_px'] ?? null)
                : null;
            $validated['reason_for_deferral'] = $validated['status'] === 'deferred'
                ? ($validated['reason_for_deferral'] === 'Custom Reason'
                    ? $validated['reason_for_deferral_custom']
                    : $validated['reason_for_deferral'])
                : null;
        }

        $result = Donor::recordProfileOrDonation($validated);
        $donor = $result['donor'];

        return response()->json([
            'message' => $result['attached'] ? 'Donation added for existing donor.' : 'Donor registered.',
            'donor' => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
            ],
        ], 201);
    }

    public function destroyDonation(Donation $donation): JsonResponse
    {
        $donation->delete();

        return response()->json(['message' => 'Donation deleted.']);
    }

    public function search(Request $request): JsonResponse
    {
        $searchTerm = trim((string) $request->query('q', ''));
        [$donors, $total] = $this->searchDonors($searchTerm);

        return response()->json([
            'total' => $total,
            'donors' => $donors->map(fn (Donor $donor) => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
            ])->values(),
        ]);
    }

    public function searchByLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        $year = (int) $validated['year'];
        $location = mb_strtolower(trim((string) ($validated['location'] ?? '')));
        $like = $location !== '' ? '%'.addcslashes($location, '%_\\').'%' : null;

        $query = Donor::query()->whereHas('donations', function (Builder $donations) use ($year, $like): void {
            $donations
                ->whereYear('donated_on', $year)
                ->where(function (Builder $status): void {
                    $status->whereNull('status')->orWhere('status', '!=', 'deferred');
                });

            if ($like !== null) {
                $donations->where(function (Builder $matchingLocation) use ($like): void {
                    $matchingLocation
                        ->whereRaw('LOWER(donors.barangay) like ?', [$like])
                        ->orWhereRaw('LOWER(donors.municipality) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(donors.province, \'\')) like ?', [$like])
                        ->orWhereRaw('LOWER(donations.location) like ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(donations.mbd_date, \'\')) like ?', [$like]);
                });
            }
        })->orderBy('full_name');

        $total = (clone $query)->count();
        $donors = $query
            ->limit(100)
            ->get(['id', 'full_name', 'last_name', 'first_name', 'middle_name', 'suffix']);

        return response()->json([
            'total' => $total,
            'donors' => $donors->map(fn (Donor $donor) => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
            ])->values(),
        ]);
    }

    public function destroyAll(): JsonResponse
    {
        $deleted = Donor::query()->delete();

        return response()->json([
            'message' => $deleted === 1 ? '1 donor was deleted.' : "{$deleted} donors were deleted.",
            'total_deleted' => $deleted,
        ]);
    }

    public function show(Donor $donor): JsonResponse
    {
        $donor->load(['donations' => fn ($query) => $query->orderByDesc('donated_on')]);
        $latestDonation = $donor->donations->first();

        return response()->json([
            'donor' => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
                'full_name' => $donor->getAttributes()['full_name'] ?? '',
                'first_name' => $donor->first_name,
                'middle_name' => $donor->middle_name,
                'last_name' => $donor->last_name,
                'suffix' => $donor->suffix,
                'blood_type' => $donor->blood_type,
                'age' => (int) $donor->age,
                'gender' => $donor->gender,
                'birthdate' => BloodBank::formatDate($donor->birthdate),
                'birthdate_value' => $donor->birthdate?->toDateString(),
                'contact' => $donor->contact,
                'address' => $donor->formattedAddress(),
                'barangay' => $donor->barangay,
                'municipality' => $donor->municipality,
                'province' => $donor->province,
                'registered_date' => BloodBank::formatDate($donor->registered_date),
                'donation_count' => $donor->donations->count(),
                'latest_donation' => $latestDonation === null ? null : [
                    'id' => $latestDonation->id,
                    'date' => $latestDonation->donated_on?->toDateString(),
                    'mbd_date' => $latestDonation->mbd_date
                        ? preg_replace('/\s*,\s*/', ', ', $latestDonation->mbd_date)
                        : '',
                    'status' => match ($latestDonation->status) {
                        'passed' => 'Passed',
                        'deferred' => 'Deferred',
                        default => '—',
                    },
                    'status_value' => $latestDonation->status,
                    'serial_number' => $latestDonation->serial_number,
                    'reserved_for_px' => $latestDonation->reserved_for_px,
                    'reason_for_deferral' => $latestDonation->reason_for_deferral,
                    'remarks' => $latestDonation->remarks,
                ],
                'donations' => $donor->donations->map(fn ($donation) => [
                    'id' => $donation->id,
                    'date' => BloodBank::formatDate($donation->donated_on),
                    'location' => $donation->location,
                    'bags' => $donation->bags ?? 1,
                    'blood_type' => $donation->blood_type ?: $donor->blood_type,
                    'mbd_date' => $donation->mbd_date
                        ? preg_replace('/\s*,\s*/', ', ', $donation->mbd_date)
                        : '—',
                    'status' => match ($donation->status) {
                        'deferred' => 'Deferred',
                        'passed' => 'Passed',
                        default => 'Completed',
                    },
                ])->values(),
            ],
        ]);
    }

    public function updateDonor(Request $request, Donor $donor): JsonResponse
    {
        $this->mergeMbdDate($request);
        $usesNameParts = $request->exists('first_name') || $request->exists('last_name');
        $latestDonation = $donor->donations()->orderByDesc('donated_on')->orderByDesc('id')->first();
        $validated = $request->validate([
            'full_name' => [$usesNameParts ? 'nullable' : 'required', 'string', 'max:255'],
            'first_name' => [$usesNameParts ? 'required' : 'nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [$usesNameParts ? 'required' : 'nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV'])],
            'blood_type' => ['required', Rule::in(BloodBank::BLOOD_TYPES)],
            'age' => ['required', 'integer', 'min:16', 'max:70'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'contact' => ['required', 'string', 'max:50'],
            'barangay' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'mbd_date' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['passed', 'deferred'])],
            'serial_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('donations', 'serial_number')->ignore($latestDonation?->id),
                Rule::requiredIf(fn () => $request->input('status') === 'passed'),
            ],
            'reserved_for_px' => ['nullable', 'string', 'max:255'],
            'reason_for_deferral' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred'),
                Rule::in(['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason']),
            ],
            'reason_for_deferral_custom' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => $request->input('status') === 'deferred'
                    && $request->input('reason_for_deferral') === 'Custom Reason'),
            ],
        ]);

        $fullName = $usesNameParts
            ? DonorController::displayName(
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['suffix'] ?? null,
            )
            : trim($validated['full_name']);
        $duplicate = Donor::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($fullName)])
            ->whereKeyNot($donor->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['full_name' => ['A donor with this name already exists.']]);
        }

        $attributes = [
            'full_name' => $fullName,
            'blood_type' => $validated['blood_type'],
            'age' => $validated['age'] ?? null,
            'gender' => $validated['gender'],
            'contact' => trim((string) ($validated['contact'] ?? '')),
            'barangay' => trim((string) ($validated['barangay'] ?? '')),
            'municipality' => trim((string) ($validated['municipality'] ?? '')),
            'province' => trim((string) ($validated['province'] ?? ''))
                ?: BloodBank::provinceForMunicipality((string) ($validated['municipality'] ?? '')),
            'birthdate' => $validated['birthdate'] ?? $donor->birthdate,
        ];

        if ($usesNameParts) {
            $attributes['first_name'] = Donor::storedNamePart($validated['first_name']);
            $attributes['middle_name'] = Donor::storedNamePart($validated['middle_name'] ?? null);
            $attributes['last_name'] = Donor::storedNamePart($validated['last_name']);
            $attributes['suffix'] = Donor::storedNamePart($validated['suffix'] ?? null);
        } elseif (strcasecmp((string) ($donor->getAttributes()['full_name'] ?? ''), $fullName) !== 0) {
            $attributes += ['first_name' => null, 'middle_name' => null, 'last_name' => null, 'suffix' => null];
        }

        try {
            DB::transaction(function () use ($donor, $attributes, $validated, $latestDonation, $request): void {
                $donor->update($attributes);
                $status = $validated['status'] ?? null;
                if (! in_array($status, ['passed', 'deferred'], true)) {
                    return;
                }

                $mbdDate = trim((string) ($validated['mbd_date'] ?? ''));
                $changes = [
                    'mbd_date' => $mbdDate !== '' ? $mbdDate : null,
                    'status' => $status,
                    'blood_type' => $donor->blood_type,
                    'serial_number' => $status === 'passed' ? trim((string) ($validated['serial_number'] ?? '')) : null,
                    'reserved_for_px' => $status === 'passed' ? (trim((string) ($validated['reserved_for_px'] ?? '')) ?: null) : null,
                    'reason_for_deferral' => $status === 'deferred'
                        ? (($validated['reason_for_deferral'] ?? '') === 'Custom Reason'
                            ? trim((string) ($validated['reason_for_deferral_custom'] ?? ''))
                            : ($validated['reason_for_deferral'] ?? null))
                        : null,
                    'bags' => $status === 'deferred' ? 0 : max(1, (int) ($latestDonation?->bags ?? 1)),
                ];
                if ($mbdDate !== '') {
                    $parsedDate = DonationScreening::parseMbdDate($mbdDate);
                    if ($parsedDate === null) {
                        throw ValidationException::withMessages([
                            'mbd_date' => 'Enter a venue and date, such as WI 1/01/2025.',
                        ]);
                    }
                    $changes['donated_on'] = $parsedDate;
                    if ($request->filled('mbd_on')) {
                        $changes['mbd_date'] = DonationScreening::composeMbdDate(
                            (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom($mbdDate)),
                            $parsedDate,
                        );
                    }
                    $changes['location'] = BloodBank::formatAssignedAddress(
                        (string) ($validated['barangay'] ?? ''),
                        (string) ($validated['municipality'] ?? ''),
                        $validated['province'] ?? null,
                    );
                }
                if ($latestDonation === null) {
                    $donor->donations()->create([
                        'donated_on' => $changes['donated_on'] ?? now()->toDateString(),
                        ...$changes,
                    ]);
                } else {
                    $latestDonation->update($changes);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'full_name' => ['A donor with this name already exists.'],
            ]);
        }

        return response()->json([
            'message' => 'Donor updated.',
            'donor' => [
                'id' => $donor->id,
                'name' => DonorController::displayNameFor($donor),
            ],
        ]);
    }

    public function destroyDonor(Donor $donor): JsonResponse
    {
        $donor->delete();

        return response()->json(['message' => 'Donor deleted.']);
    }

    public function storeDonation(Request $request, Donor $donor): JsonResponse
    {
        $this->mergeMbdDate($request);

        $validated = $request->validate([
            'mbd_date' => ['required', 'string', 'max:100'],
        ]);

        $parsed = DonationScreening::parseMbdDate((string) $validated['mbd_date']);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'mbd_date' => 'Enter a valid date, such as 01/23/2025.',
            ]);
        }

        $mbdDate = DonationScreening::composeMbdDate(
            (string) ($request->input('mbd_type') ?: DonationScreening::mbdTypeFrom((string) $validated['mbd_date'])),
            $parsed,
        );
        $donation = $donor->donations()->create([
            'donated_on' => $parsed,
            'blood_type' => $donor->blood_type,
            'location' => DonationScreening::mbdTypeFrom((string) ($request->input('mbd_type') ?: $validated['mbd_date'])),
            'bags' => 1,
            'mbd_date' => $mbdDate,
        ]);

        return response()->json([
            'message' => 'Donation saved.',
            'donation' => [
                'id' => $donation->id,
                'date' => BloodBank::formatDate($donation->donated_on),
                'location' => $donation->location,
                'bags' => (int) $donation->bags,
            ],
        ], 201);
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
}
