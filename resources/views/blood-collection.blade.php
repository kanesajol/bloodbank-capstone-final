@extends('layouts.app')

@section('title', 'CRH Blood Bank — Blood Collection')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/blood-collection.css') }}">
    <style>
        .module-page--full-page .search-donor > :not(script):not(#register-modal):not(#donor-modal):not(#edit-donor-modal) {
            display: none !important;
        }
        .module-page--full-page .page-panel {
            position: static;
            inset: auto;
            z-index: auto;
            display: block;
            width: 100%;
            padding: 0;
            background: transparent;
        }
        .module-page--full-page .page-panel[hidden] {
            display: none !important;
        }
        .module-page--full-page .page-panel .modal-card {
            width: 100%;
            max-width: none;
            max-height: none;
            overflow: visible;
            border: 1px solid #f1d7d7;
            box-shadow: none;
        }
        #name-search-table-wrap {
            width: 100%;
            overflow-x: hidden;
        }
        #name-donor-table {
            width: 100%;
            table-layout: fixed;
        }
        #name-donor-table th,
        #name-donor-table td {
            padding: 8px 12px;
            white-space: normal;
        }
        #name-donor-table .donor-name-link,
        #name-donor-table .donor-name-link strong {
            white-space: nowrap;
        }
        #name-donor-table .name-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        #name-donor-table .name-search-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        #name-donor-table .action-icon {
            width: 28px;
            height: 28px;
            padding: 0;
            justify-content: center;
        }
        #name-donor-table .action-icon svg {
            width: 15px;
            height: 15px;
            display: block;
        }
        #name-donor-table .action-icon-delete {
            color: #990000;
        }
        #name-search-table-wrap {
            position: relative;
        }
        #name-donor-body {
            transition: opacity 0.15s ease;
        }
        #name-donor-body.is-loading {
            opacity: 0.45;
        }
        #name-search-table-wrap.is-loading::after {
            content: '';
            position: absolute;
            top: 2.5rem;
            left: 50%;
            width: 18px;
            height: 18px;
            margin-left: -9px;
            border: 2px solid #e2e8f0;
            border-top-color: #990000;
            border-radius: 50%;
            animation: name-search-spin 0.7s linear infinite;
        }
        @keyframes name-search-spin {
            to { transform: rotate(360deg); }
        }
        .dashboard,
        .dashboard__content {
            height: auto;
            max-height: none;
            overflow: visible;
        }
        .dashboard__main {
            flex: 1;
            overflow: visible;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 100%;
            padding: 0.6rem 0.85rem;
        }
        .module-page {
            flex: 1;
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
        }
        .module-page__content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            overflow: visible;
            display: flex;
            flex-direction: column;
            padding: 0.65rem 0.75rem;
        }
        .flash-banner {
            margin: 0 0 0.45rem;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .search-donor {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            overflow: visible;
        }
        .collection-header-bar {
            margin: 0;
            padding-bottom: 0.35rem;
            flex-shrink: 0;
        }
        .collection-header-bar h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--color-red-primary);
        }
        .mode-tabs {
            flex-shrink: 0;
        }
        .mode-tab {
            padding: 4px 10px;
            font-size: 0.75rem;
        }
        .search-input,
        .filter-select,
        .btn-register-donor {
            font-size: 0.75rem;
        }
        .search-input,
        .filter-select {
            height: 32px;
            box-sizing: border-box;
        }
        .results-count {
            font-size: 0.75rem;
            margin: 0;
            flex-shrink: 0;
        }
        .mode-panel {
            flex: 1;
            overflow: visible;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        #name-search-table-wrap,
        #location-year-results {
            width: 100%;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
        }
        .donor-table th,
        .donor-table td,
        #name-donor-table th,
        #name-donor-table td {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .donor-table th,
        #name-donor-table th {
            font-weight: 600;
        }
        .donor-table .blood-type-badge,
        #name-donor-table .blood-type-badge {
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            line-height: 1.2;
        }
        .name-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.75rem;
            grid-column: 1 / -1;
        }
        @media (max-width: 640px) {
            .name-fields {
                grid-template-columns: 1fr;
            }
        }
        .register-sections {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .form-section {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .form-section-title {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 700;
            color: #990000;
        }
        .status-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
        }
        .status-toggle label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: #374151;
        }
        .results-count-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .btn-delete-all {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #E8C8C8;
            background: #fff;
            color: #990000;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-delete-all svg {
            width: 14px;
            height: 14px;
        }
        .donor-detail-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #E8C8C8;
        }
        @media (max-width: 640px) {
            .search-donor .results-count-row {
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                flex-wrap: nowrap;
                width: 100%;
                min-width: 0;
                gap: 8px;
            }
            .search-donor .results-count {
                flex: 1 1 auto;
                min-width: 0;
                text-align: left;
            }
            .search-donor .btn-delete-all {
                flex: 0 0 auto;
                margin-left: auto;
            }
            .search-donor #location-year-filters {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 8px;
                width: 100%;
                min-width: 0;
            }
            .search-donor #location-year-filters .search-input-wrap {
                flex: initial;
                width: auto;
                min-width: 0;
                max-width: none;
            }
            .search-donor #location-year-filters .search-input {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                box-sizing: border-box;
            }
            .search-donor #location-year-filters .filter-year-wrap {
                flex: 0 0 auto;
                justify-content: center;
                gap: 4px;
                min-height: 44px;
                padding: 0 8px;
                border: 1px solid #E8C8C8;
                border-radius: 8px;
                background: #fff;
                white-space: nowrap;
            }
            .search-donor #location-year-filters .filter-select {
                width: 76px;
                min-width: 0;
                padding: 6px 4px;
            }
            .search-donor #location-year-results .record-item {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .search-donor #location-year-results .record-donor-info {
                min-width: 0;
                flex-wrap: wrap;
            }
            .search-donor #location-year-results .record-donor-info .donor-name-link {
                flex: 1 1 100%;
                min-width: 0;
                overflow-wrap: anywhere;
            }
            .search-donor #location-year-results .record-date-badge {
                align-self: flex-start;
                max-width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $closeDonorUrl = route('blood-collection', array_filter([
            'mode' => $mode,
            'location' => $mode === 'location-year' ? $locationQuery : null,
            'year' => $mode === 'location-year' ? $year : null,
        ]));
    @endphp
    <div class="module-page {{ $pageView ? 'module-page--full-page' : '' }}">
        <div class="module-page__content">
            <div class="search-donor">
                <div class="collection-header-bar">
                    <h2>Blood Collection Management</h2>
                    <a class="btn-register-donor" href="{{ route('blood-collection', ['mode' => $mode, 'register' => 1]) }}">
                        <i class="ti ti-user-plus"></i> Register / Returning Donor
                    </a>
                </div>
                <div class="mode-tabs">
                    <button type="button" class="mode-tab {{ $mode === 'name' ? 'active' : '' }}" onclick="window.location='{{ route('blood-collection', ['mode' => 'name']) }}'">
                        <i class="ti ti-search"></i> Search by Name
                    </button>
                    <button type="button" class="mode-tab {{ $mode === 'location-year' ? 'active' : '' }}" onclick="window.location='{{ route('blood-collection', ['mode' => 'location-year']) }}'">
                        <i class="ti ti-calendar"></i> Search by Location &amp; Year
                    </button>
                </div>

                @if ($mode === 'name')
                    <div class="mode-panel">
                        <div class="search-bar-wrap">
                            <div class="search-input-wrap">
                                <i class="ti ti-search search-icon"></i>
                                <input id="name-query" name="q" value="{{ $searchTerm }}" type="text" class="search-input" placeholder="Search name" autocomplete="off">
                            </div>
                            <button type="button" class="btn-clear" id="name-clear" hidden>Clear</button>
                        </div>
                        <div class="results-count-row">
                        <p class="results-count">
                                <span id="name-count">{{ $donorTotal }}</span> donor{{ $donorTotal === 1 ? '' : 's' }} found
                            <span id="name-query-label" hidden></span>
                                <span id="name-limit-note">@if ($donorTotal > $donors->count()) Showing {{ $donors->count() }} of {{ $donorTotal }}. Type a name to narrow the list.@endif</span>
                            </p>
                            @if ($donors->isNotEmpty())
                                <button type="button" class="btn-delete-all" data-delete-url="{{ route('donors.destroy-all') }}" data-delete-name="All donors" data-return-to="blood-collection">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    Delete All
                                </button>
                            @endif
                        </div>
                        <div class="table-wrap" id="name-search-table-wrap">
                            <table class="donor-table w-full table-fixed" id="name-donor-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody id="name-donor-body">
                                    @forelse ($donors as $donor)
                                        <tr class="donor-row" data-search="{{ mb_strtolower(\App\Http\Controllers\DonorController::displayNameFor($donor)) }}" data-profile-url="{{ route('blood-collection', ['mode' => 'name', 'donor' => $donor->id], false) }}">
                                            <td>
                                                <div class="name-row">
                                                    <span class="donor-name-link"><strong>{{ \App\Http\Controllers\DonorController::displayNameFor($donor) }}</strong></span>
                                                    <span class="name-search-actions">
                                                        <button type="button" class="btn-view-profile action-icon" title="Edit donor" aria-label="Edit donor" data-edit-url="{{ route('blood-collection', ['mode' => 'name', 'donor' => $donor->id, 'edit' => 1], false) }}" onclick="event.stopPropagation(); openDonorEditor(this.dataset.editUrl)">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                                        </button>
                                                        <button type="button" class="btn-view-profile action-icon action-icon-delete" title="Delete record" aria-label="Delete record" data-delete-url="{{ route('donors.destroy', $donor) }}" data-delete-name="{{ \App\Http\Controllers\DonorController::displayNameFor($donor) }}" data-return-to="blood-collection" onclick="event.stopPropagation()">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr data-empty-row>
                                            <td class="empty-state">No donors match your search name.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <script>
                            (function () {
                                const input = document.getElementById('name-query');
                                const tbody = document.getElementById('name-donor-body');
                                const wrap = document.getElementById('name-search-table-wrap');
                                const count = document.getElementById('name-count');
                                const label = document.getElementById('name-query-label');
                                const clearBtn = document.getElementById('name-clear');
                                if (!input || !tbody) return;

                                const bindDeletes = (root) => {
                                    const deleteModal = document.getElementById('delete-confirm-modal');
                                    const deleteForm = document.getElementById('delete-confirm-form');
                                    const deleteName = document.getElementById('delete-confirm-name');
                                    root.querySelectorAll('[data-delete-url]').forEach((button) => {
                                        button.addEventListener('click', (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            if (!deleteModal || !deleteForm) return;
                                            deleteForm.action = button.dataset.deleteUrl;
                                            if (deleteName) deleteName.textContent = button.dataset.deleteName || '';
                                            const returnTo = deleteForm.querySelector('[name="return_to"]');
                                            if (returnTo) returnTo.value = button.dataset.returnTo || '';
                                            deleteModal.removeAttribute('hidden');
                                        });
                                    });
                                };

                                let controller;
                                const run = () => {
                                    const term = input.value.trim();
                                    const url = new URL(@json(route('blood-collection')), window.location.origin);
                                    url.searchParams.set('mode', 'name');
                                    if (term !== '') url.searchParams.set('q', term);
                                    controller?.abort();
                                    controller = new AbortController();
                                    const current = controller;
                                    tbody.classList.add('is-loading');
                                    wrap?.classList.add('is-loading');
                                    fetch(url.toString(), {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                        signal: current.signal,
                                    })
                                        .then((response) => response.ok ? response.text() : Promise.reject(response))
                                        .then((html) => {
                                            const doc = new DOMParser().parseFromString(html, 'text/html');
                                            const next = doc.getElementById('name-donor-body');
                                            if (next) tbody.innerHTML = next.innerHTML;
                                            const nextCount = doc.getElementById('name-count');
                                            if (count && nextCount) count.textContent = nextCount.textContent;
                                            const note = document.getElementById('name-limit-note');
                                            const nextNote = doc.getElementById('name-limit-note');
                                            if (note && nextNote) note.textContent = nextNote.textContent;
                                            if (label) {
                                                label.hidden = term === '';
                                                label.textContent = term === '' ? '' : ' for "' + term + '"';
                                            }
                                            if (clearBtn) clearBtn.hidden = term === '';
                                            bindDeletes(tbody);
                                            history.replaceState(null, '', url.toString());
                                        })
                                        .catch((error) => {
                                            if (error.name === 'AbortError') return;
                                        })
                                        .finally(() => {
                                            if (controller !== current) return;
                                            tbody.classList.remove('is-loading');
                                            wrap?.classList.remove('is-loading');
                                        });
                                };

                                input.addEventListener('input', () => {
                                    window.clearTimeout(window.nameSearchTimer);
                                    window.nameSearchTimer = window.setTimeout(run, 300);
                                });
                            })();
                        </script>
                    </div>
                @else
                    <div class="mode-panel">
                        <form id="location-year-filters" class="filters-bar" method="GET" action="{{ route('blood-collection') }}">
                            <input type="hidden" name="mode" value="location-year">
                            <div class="search-input-wrap">
                                <i class="ti ti-map-pin search-icon"></i>
                                <input id="location-year-query" name="location" value="{{ $locationQuery }}" type="text" class="search-input" placeholder="Search location" autocomplete="off">
                            </div>
                            <div class="filter-year-wrap">
                                <label>Year:</label>
                                <select name="year" class="filter-select" onchange="this.form.submit()">
                                    @foreach ($years as $option)
                                        <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>

                        <div id="location-year-results">
                            <div class="month-groups">
                                    <div class="empty-state" data-location-empty @if (count($monthGroups) > 0) hidden @endif>
                                        @if (mb_strlen($locationQuery) < 2)
                                            Type a location to see donors in <strong>{{ $year }}</strong>.
                                        @else
                                            No donation records found for <strong>{{ $locationQuery }}</strong> in <strong>{{ $year }}</strong>.
                                @endif
                                    </div>
                                @foreach ($monthGroups as $group)
                                    <div class="month-group">
                                        <div class="month-title">
                                            <span><i class="ti ti-calendar"></i> {{ $group['month'] }} {{ $year }}</span>
                                                <span class="badge" data-month-count>{{ count($group['records']) }} Donor{{ count($group['records']) === 1 ? '' : 's' }}</span>
                                        </div>
                                        <ul class="record-list">
                                            @foreach ($group['records'] as $record)
                                                    <li class="record-item" data-search="{{ mb_strtolower($record['venue'].' '.$record['name']) }}" data-url="{{ route('blood-collection', ['mode' => 'location-year', 'year' => $year, 'donor' => $record['id'], 'location' => $locationQuery !== '' ? $locationQuery : null], false) }}">
                                                    <div class="record-donor-info">
                                                        <span class="donor-name-link">{{ $record['name'] }}</span>
                                                        <span class="blood-type-badge sm">{{ $record['blood_type'] }}</span>
                                                            <span class="record-sub">{{ $record['venue'] }}</span>
                                                            <span class="badge">{{ $record['count'] }} Donation{{ $record['count'] === 1 ? '' : 's' }}</span>
                                                    </div>
                                                    <div class="record-date-badge">
                                                        <i class="ti ti-calendar-event"></i>
                                                        <strong>{{ \App\Support\BloodBank::formatDate($record['date']) }}</strong>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <script>
                            (function () {
                                const form = document.getElementById('location-year-filters');
                                const input = document.getElementById('location-year-query');
                                const results = document.getElementById('location-year-results');
                                if (!form || !input || !results) return;

                                let timer;
                                let controller;
                                input.addEventListener('input', () => {
                                    window.clearTimeout(timer);
                                    timer = window.setTimeout(() => {
                                        const params = new URLSearchParams(new FormData(form));
                                        params.set('mode', 'location-year');
                                        params.set('location', input.value.trim());
                                        const url = form.action + '?' + params.toString();
                                        controller?.abort();
                                        controller = new AbortController();
                                        const current = controller;
                                        fetch(url, {
                                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                            signal: current.signal,
                                        })
                                            .then((response) => response.ok ? response.text() : Promise.reject(response))
                                            .then((html) => {
                                                if (controller !== current) return;
                                                const doc = new DOMParser().parseFromString(html, 'text/html');
                                                const next = doc.getElementById('location-year-results');
                                                if (next) results.innerHTML = next.innerHTML;
                                                history.replaceState(null, '', url);
                                            })
                                            .catch((error) => {
                                                if (error.name === 'AbortError') return;
                                            });
                                    }, 300);
                                });
                                form.addEventListener('submit', (event) => {
                                    if (document.activeElement === input) event.preventDefault();
                                });
                            })();
                        </script>
                    </div>
                @endif

                @if ($editingDonor && $selectedDonor)
                    @php $editing = old('form') === 'edit-donor'; @endphp
                    <div class="{{ $pageView === 'edit-donor' ? 'page-panel' : 'modal-backdrop' }}" id="edit-donor-modal" data-close-url="{{ $closeDonorUrl }}">
                        <div class="modal-card register-modal-card">
                            <a class="modal-close" href="{{ $closeDonorUrl }}" aria-label="Back to donor list" title="Back to donor list">
                                <i class="ti ti-x"></i>
                            </a>
                            <div class="modal-header">
                                <div class="modal-header-icon-wrap">
                                    <i class="ti ti-pencil"></i>
                                </div>
                                <div>
                                    <h3 class="modal-name">Edit Donor Info</h3>
                                </div>
                            </div>
                            <form class="register-form" method="POST" action="{{ route('donors.store') }}">
                                @csrf
                                <input type="hidden" name="form" value="edit-donor">
                                <input type="hidden" name="donor_id" value="{{ $selectedDonor->id }}">
                                @php
                                    $nameParts = $selectedDonor->nameParts();
                                    $editFirst = $editing ? old('first_name') : $nameParts['first_name'];
                                    $editMiddle = $editing ? old('middle_name') : $nameParts['middle_name'];
                                    $editLast = $editing ? old('last_name') : $nameParts['last_name'];
                                    $editSuffix = $editing ? old('suffix') : $nameParts['suffix'];
                                    $latestDonation = $selectedDonor->donations->sortByDesc('donated_on')->first();
                                    $deferralReasons = ['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason'];
                                    $editStatus = $editing ? old('status', 'passed') : ($latestDonation->status ?? 'passed');
                                    if (! in_array($editStatus, ['passed', 'deferred'], true)) {
                                        $editStatus = 'passed';
                                    }
                                    $storedReason = (string) ($editing ? old('reason_for_deferral', '') : ($latestDonation->reason_for_deferral ?? ''));
                                    $editReason = in_array($storedReason, $deferralReasons, true) ? $storedReason : ($storedReason !== '' ? 'Custom Reason' : '');
                                    $editReasonCustom = $editing ? old('reason_for_deferral_custom', '') : ($editReason === 'Custom Reason' ? $storedReason : '');
                                    $editBirthdate = $editing ? old('birthdate') : ($selectedDonor->birthdate?->format('Y-m-d') ?? '');
                                    $editGender = $editing ? old('gender') : $selectedDonor->gender;
                                    $editBloodType = $editing ? old('blood_type') : $selectedDonor->blood_type;
                                    $editMbd = \App\Support\DonationScreening::splitMbdDate($editing ? old('mbd_date') : $latestDonation?->mbd_date);
                                    if ($editing && old('mbd_type')) {
                                        $editMbd['type'] = old('mbd_type');
                                    }
                                    if ($editing && old('mbd_on')) {
                                        $editMbd['date'] = old('mbd_on');
                                    }
                                    $editProvince = $editing ? old('province') : $selectedDonor->province;
                                    $editMunicipality = $editing ? old('municipality') : $selectedDonor->municipality;
                                    $editBarangay = $editing ? old('barangay') : $selectedDonor->barangay;
                                @endphp
                                <input type="hidden" name="donation_id" value="{{ $latestDonation?->id }}">
                                <div class="register-sections">
                                    <section class="form-section">
                                        <h4 class="form-section-title">Name &amp; Contact</h4>
                                        <div class="form-grid">
                                            <div class="form-field">
                                                <label for="editLastName">Last Name / Apilyedo *</label>
                                                <input id="editLastName" name="last_name" value="{{ $editLast }}" type="text" placeholder="Last name" data-title-case required>
                                            </div>
                                            <div class="form-field">
                                                <label for="editFirstName">First Name *</label>
                                                <input id="editFirstName" name="first_name" value="{{ $editFirst }}" type="text" placeholder="First name" data-title-case required>
                                            </div>
                                            <div class="form-field">
                                                <label for="editMiddleName">Middle Name</label>
                                                <input id="editMiddleName" name="middle_name" value="{{ $editMiddle }}" type="text" placeholder="Middle name" data-title-case>
                                            </div>
                                            <div class="form-field">
                                                <label for="editSuffix">Suffix / Title</label>
                                                <select id="editSuffix" name="suffix">
                                                    <option value="" @selected($editSuffix === '' || $editSuffix === null)>None</option>
                                                    @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV'] as $suffixOption)
                                                        <option value="{{ $suffixOption }}" @selected($editSuffix === $suffixOption)>{{ $suffixOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-field">
                                                <label for="editContact">Contact #</label>
                                                <input id="editContact" name="contact" value="{{ $editing ? old('contact') : $selectedDonor->contact }}" type="tel">
                                            </div>
                                        </div>
                                    </section>
                                    <section class="form-section">
                                        <h4 class="form-section-title">Event &amp; Personal Info</h4>
                                        <div class="form-grid">
                                            <div class="form-field">
                                                <label for="editMbdOn">MBD / Date</label>
                                                <div class="mbd-control">
                                                    <select id="editMbdType" name="mbd_type" data-mbd-type aria-label="MBD or WI">
                                                        <option value="MBD" @selected($editMbd['type'] === 'MBD')>MBD</option>
                                                        <option value="WI" @selected($editMbd['type'] === 'WI')>WI</option>
                                                    </select>
                                                    <input id="editMbdOn" name="mbd_on" value="{{ $editMbd['date'] }}" type="date" data-mbd-on>
                                                </div>
                                                <input id="editMbdDate" name="mbd_date" value="{{ $editing ? old('mbd_date') : $latestDonation?->mbd_date }}" type="hidden" data-mbd-value>
                                            </div>
                                            <div class="form-field">
                                                <label for="editBirthdate">Birthdate</label>
                                                <input id="editBirthdate" name="birthdate" value="{{ $editBirthdate }}" type="date" data-birthdate>
                                            </div>
                                            <div class="form-field">
                                                <label for="editAge">Age</label>
                                                <input id="editAge" name="age" value="{{ $editing ? old('age') : $selectedDonor->age }}" type="number" min="16" max="70" data-age>
                                            </div>
                                            <div class="form-field">
                                                <label for="editGender">Sex</label>
                                                <select id="editGender" name="gender" required>
                                                    <option value="" disabled @selected($editGender === '' || $editGender === null)>Select Sex</option>
                                                    <option value="Male" @selected($editGender === 'Male')>Male</option>
                                                    <option value="Female" @selected($editGender === 'Female')>Female</option>
                                                    @if (! in_array($editGender, ['Male', 'Female', '', null], true))
                                                        <option value="{{ $editGender }}" selected>{{ $editGender }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="form-field">
                                                <label for="editBloodType">Blood Type</label>
                                                <select id="editBloodType" name="blood_type" required>
                                                    <option value="" disabled @selected($editBloodType === '' || $editBloodType === null)>Select Blood Type</option>
                                                    @foreach ($bloodTypes as $type)
                                                        <option value="{{ $type }}" @selected($editBloodType === $type)>{{ $type }}</option>
                                                    @endforeach
                                                    @if ($editBloodType && ! in_array($editBloodType, $bloodTypes, true))
                                                        <option value="{{ $editBloodType }}" selected>{{ $editBloodType }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="form-section">
                                        <h4 class="form-section-title">Address</h4>
                                        <div class="form-grid" data-location-fields>
                                            <div class="form-field">
                                                <label for="editProvince">Province</label>
                                                <select id="editProvince" name="province" data-province>
                                                    <option value="">Select province</option>
                                                    @foreach ($provinces as $provinceOption)
                                                        <option value="{{ $provinceOption }}" @selected($editProvince === $provinceOption)>{{ $provinceOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-field">
                                                <label for="editMunicipality">Municipality / City</label>
                                                <select id="editMunicipality" name="municipality" data-municipality data-selected="{{ $editMunicipality }}">
                                                    <option value="">Select municipality</option>
                                                </select>
                                            </div>
                                            <div class="form-field">
                                                <label for="editBarangay">Barangay</label>
                                                <select id="editBarangay" data-barangay data-selected="{{ $editBarangay }}">
                                                    <option value="">Select barangay</option>
                                                </select>
                                                <input id="editBarangayText" data-barangay-text value="{{ $editBarangay }}" type="text" data-title-case placeholder="Barangay" hidden>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="form-section">
                                        <h4 class="form-section-title">Donation Outcome</h4>
                                        <div class="form-field">
                                            <span class="form-section-title">Donor Status</span>
                                            <div class="status-toggle">
                                                <label for="editStatusPassed">
                                                    <input id="editStatusPassed" name="status" value="passed" type="radio" @checked($editStatus === 'passed')>
                                                    PASSED
                                                </label>
                                                <label for="editStatusDeferred">
                                                    <input id="editStatusDeferred" name="status" value="deferred" type="radio" @checked($editStatus === 'deferred')>
                                                    DEFERRED
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-grid" data-outcome="passed" @if($editStatus !== 'passed') hidden @endif>
                                            <div class="form-field">
                                                <label for="editSerialNumber">Serial #</label>
                                                <input id="editSerialNumber" name="serial_number" value="{{ $editing ? old('serial_number') : $latestDonation?->serial_number }}" type="text" placeholder="Blood bag barcode">
                                            </div>
                                            <div class="form-field">
                                                <label for="editReservedForPx">Reserved for Patient (Optional)</label>
                                                <input id="editReservedForPx" name="reserved_for_px" value="{{ $editing ? old('reserved_for_px') : $latestDonation?->reserved_for_px }}" type="text" data-title-case>
                                            </div>
                                        </div>
                                        <div class="form-grid" data-outcome="deferred" @if($editStatus !== 'deferred') hidden @endif>
                                            <div class="form-field">
                                                <label for="editDeferralReason">Reason for Deferral</label>
                                                <select id="editDeferralReason" name="reason_for_deferral" data-deferral-reason>
                                                    <option value="" disabled @selected($editReason === '')>Select reason</option>
                                                    @foreach ($deferralReasons as $reasonOption)
                                                        <option value="{{ $reasonOption }}" @selected($editReason === $reasonOption)>{{ $reasonOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-field" data-custom-reason @if($editReason !== 'Custom Reason') hidden @endif>
                                                <label for="editDeferralCustom">Custom Reason</label>
                                                <input id="editDeferralCustom" name="reason_for_deferral_custom" value="{{ $editReasonCustom }}" type="text" data-title-case>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                                @if ($errors->any() && $editing)
                                    <p class="form-error">{{ $errors->first() }}</p>
                                @endif
                                <div class="modal-actions">
                                    <a class="btn-cancel" href="{{ $closeDonorUrl }}">Cancel</a>
                                    <button type="submit" class="btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @elseif ($selectedDonor)
                    @php
                        $history = $selectedDonor->donations->sortByDesc('donated_on')->values();
                        $latest = $history->first();
                        $latestId = $latest?->id;
                        $outcomeLabel = match ($latest?->status) {
                            'passed' => 'Passed',
                            'deferred' => 'Deferred',
                            default => '—',
                        };
                    @endphp
                    <div class="{{ $pageView === 'donor' ? 'page-panel' : 'modal-backdrop' }}" id="donor-modal">
                        <div class="modal-card donor-detail-card">
                            <a class="modal-close" href="{{ $closeDonorUrl }}" aria-label="Back to donor list" title="Back to donor list">
                                <i class="ti ti-x"></i>
                            </a>
                            <div class="modal-header">
                                <div>
                                    <h3 class="modal-name">{{ \App\Http\Controllers\DonorController::displayNameFor($selectedDonor) }}</h3>
                                    @if ($selectedDonor->formattedAddress() !== '')
                                    <span class="modal-sub">{{ $selectedDonor->formattedAddress() }}</span>
                                    @endif
                                </div>
                                <span class="blood-type-badge lg">{{ $selectedDonor->blood_type }}</span>
                            </div>
                            <div class="modal-grid">
                                <div class="modal-field"><span>Age:</span><strong>{{ $selectedDonor->age }} yrs old</strong></div>
                                <div class="modal-field"><span>Sex:</span><strong>{{ $selectedDonor->gender }}</strong></div>
                                <div class="modal-field"><span>Birthdate:</span><strong>{{ $selectedDonor->birthdate ? \App\Support\BloodBank::formatDate($selectedDonor->birthdate) : '—' }}</strong></div>
                                <div class="modal-field"><span>Contact:</span><strong>{{ $selectedDonor->contact }}</strong></div>
                                <div class="modal-field"><span>Registered Date:</span><strong>{{ \App\Support\BloodBank::formatDate($selectedDonor->registered_date) }}</strong></div>
                                <div class="modal-field"><span>Donor Address:</span><strong>{{ $selectedDonor->formattedAddress() !== '' ? $selectedDonor->formattedAddress() : '—' }}</strong></div>
                                <div class="modal-field modal-field-wide"><span>MBD / Date:</span><strong>{{ $latest?->mbd_date ? preg_replace('/\s*,\s*/', ', ', $latest->mbd_date) : '—' }}</strong></div>
                                <div class="modal-field"><span>Status:</span><strong>{{ $outcomeLabel }}</strong></div>
                                <div class="modal-field"><span>Serial #:</span><strong>{{ $latest?->serial_number ?: '—' }}</strong></div>
                                <div class="modal-field"><span>Reserved for Patient:</span><strong>{{ $latest?->reserved_for_px ?: '—' }}</strong></div>
                                <div class="modal-field"><span>Reason for Deferral:</span><strong>{{ $latest?->reason_for_deferral ?: '—' }}</strong></div>
                                <div class="modal-field"><span>Remarks:</span><strong>{{ $latest?->remarks ?: '—' }}</strong></div>
                            </div>
                            <div class="modal-total-banner">
                                <div>
                                    <span>Total Number of Donations:</span>
                                    <strong class="total-count-val">{{ $selectedDonor->donations->count() }}</strong>
                                </div>
                            </div>
                            <h4 class="modal-history-title">Complete Donation History</h4>
                            <div class="history-table-wrap">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th class="col-date">Date</th>
                                            <th class="col-type">Blood Type</th>
                                            <th>MBD / Date</th>
                                            <th class="col-bags">Bags</th>
                                            <th class="col-status">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($history as $rec)
                                            <tr class="{{ $rec->id === $latestId ? 'row-new-update' : '' }}">
                                                <td class="col-date">
                                                    <div class="history-date">
                                                    <strong>{{ \App\Support\BloodBank::formatDate($rec->donated_on) }}</strong>
                                                    @if ($rec->id === $latestId)
                                                        <span class="new-badge">new update</span>
                                                    @endif
                                                    </div>
                                                </td>
                                                <td class="col-type"><span class="blood-type-badge sm">{{ $rec->blood_type ?: $selectedDonor->blood_type }}</span></td>
                                                <td class="history-venue">{{ $rec->mbd_date ? preg_replace('/\s*,\s*/', ', ', $rec->mbd_date) : '—' }}</td>
                                                <td class="col-bags">{{ $rec->bags ?? 1 }}</td>
                                                <td class="col-status">
                                                    <span class="status-pill verified">{{ $rec->status === 'deferred' ? 'Deferred' : ($rec->status === 'passed' ? 'Passed' : 'Completed') }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="donor-detail-actions">
                                <a class="btn-view-profile action-icon" title="Edit donor" aria-label="Edit donor" href="{{ route('blood-collection', ['mode' => 'name', 'donor' => $selectedDonor->id, 'edit' => 1]) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                </a>
                                <button type="button" class="btn-view-profile action-icon action-icon-delete" title="Delete record" aria-label="Delete record" data-delete-url="{{ route('donors.destroy', $selectedDonor) }}" data-delete-name="{{ \App\Http\Controllers\DonorController::displayNameFor($selectedDonor) }}" data-return-to="blood-collection">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <script>
                    let donorProfileRequest = 0;
                    function closeDonorModal() {
                        const modal = document.getElementById('donor-modal');
                        if (!modal) return;
                        const url = new URL(window.location.href);
                        url.searchParams.delete('donor');
                        url.searchParams.delete('edit');
                        history.replaceState(null, '', url);
                        modal.remove();
                    }
                    function bindDeleteButtons(root) {
                        const deleteModal = document.getElementById('delete-confirm-modal');
                        const deleteForm = document.getElementById('delete-confirm-form');
                        const deleteName = document.getElementById('delete-confirm-name');
                        root.querySelectorAll('[data-delete-url]').forEach((button) => {
                            if (button.dataset.deleteBound === '1') return;
                            button.dataset.deleteBound = '1';
                            button.addEventListener('click', (event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                if (!deleteModal || !deleteForm) return;
                                deleteForm.action = button.dataset.deleteUrl;
                                if (deleteName) deleteName.textContent = button.dataset.deleteName || '';
                                const returnTo = deleteForm.querySelector('[name="return_to"]');
                                if (returnTo) returnTo.value = button.dataset.returnTo || '';
                                deleteModal.removeAttribute('hidden');
                            });
                        });
                    }
                    function bindDonorModal(modal) {
                        if (!modal || modal.dataset.bound === '1') return;
                        modal.dataset.bound = '1';
                        bindDeleteButtons(modal);
                        modal.addEventListener('click', (event) => {
                            if (event.target === modal) closeDonorModal();
                        });
                    }
                    function closeEditDonorModal() {
                        const modal = document.getElementById('edit-donor-modal');
                        if (!modal) return;
                        const url = new URL(window.location.href);
                        url.searchParams.delete('donor');
                        url.searchParams.delete('edit');
                        history.replaceState(null, '', url);
                        modal.remove();
                    }
                    function bindBirthdateAge(form) {
                        if (!form || form.dataset.birthdateAgeBound === '1') return;
                        const birthdate = form.querySelector('[data-birthdate]');
                        const age = form.querySelector('[data-age]');
                        if (!birthdate || !age) return;
                        form.dataset.birthdateAgeBound = '1';
                        birthdate.addEventListener('change', () => {
                            if (!birthdate.value) return;
                            const born = new Date(birthdate.value + 'T00:00:00');
                            const today = new Date();
                            let years = today.getFullYear() - born.getFullYear();
                            const month = today.getMonth() - born.getMonth();
                            if (month < 0 || (month === 0 && today.getDate() < born.getDate())) years -= 1;
                            if (years >= 16 && years <= 70) age.value = String(years);
                        });
                    }
                    function bindEditDonorForm(modal) {
                        const form = modal?.querySelector('form');
                        if (!form || form.dataset.outcomeBound === '1') return;
                        form.dataset.outcomeBound = '1';
                        const passed = form.querySelector('[data-outcome="passed"]');
                        const deferred = form.querySelector('[data-outcome="deferred"]');
                        const reason = form.querySelector('[data-deferral-reason]');
                        const custom = form.querySelector('[data-custom-reason]');
                        const sync = () => {
                            const status = form.querySelector('input[name="status"]:checked')?.value || 'passed';
                            if (passed) passed.hidden = status !== 'passed';
                            if (deferred) deferred.hidden = status !== 'deferred';
                            if (custom && reason) custom.hidden = reason.value !== 'Custom Reason';
                        };
                        form.querySelectorAll('input[name="status"]').forEach((input) => {
                            input.addEventListener('change', sync);
                        });
                        reason?.addEventListener('change', sync);
                        bindBirthdateAge(form);
                        sync();
                    }
                    function openDonorEditor(url) {
                        window.location.href = url;
                    }
                    function openDonorProfile(url) {
                        window.location.href = url;
                    }
                    document.addEventListener('click', (event) => {
                        if (event.target.closest('button, a, input, select, textarea, label')) return;
                        const row = event.target.closest('tr.donor-row');
                        if (row?.dataset.profileUrl) {
                            openDonorProfile(row.dataset.profileUrl);
                            return;
                        }
                        const item = event.target.closest('.record-item');
                        if (!item?.dataset.url) return;
                        const url = new URL(item.dataset.url, window.location.origin);
                        const location = document.getElementById('location-year-query')?.value.trim() || '';
                        if (location !== '') url.searchParams.set('location', location);
                        openDonorProfile(url.pathname + url.search);
                    });
                    document.addEventListener('DOMContentLoaded', () => {
                        bindDonorModal(document.getElementById('donor-modal'));
                        bindBirthdateAge(document.getElementById('register-new-panel'));
                        bindEditDonorForm(document.getElementById('edit-donor-modal'));
                    });
                </script>

                <div id="register-modal" class="{{ $pageView === 'register-donor' ? 'page-panel' : 'modal-backdrop' }}" @unless($openRegister) hidden @endunless>
                    <div class="modal-card register-modal-card">
                        <a class="modal-close" href="{{ $closeDonorUrl }}" aria-label="Back to donor list" title="Back to donor list">
                            <i class="ti ti-x"></i>
                        </a>
                        <div class="modal-header">
                            <div class="modal-header-icon-wrap">
                                <i class="ti {{ $registerTab === 'new' ? 'ti-user-plus' : 'ti-refresh' }}" id="register-modal-icon"></i>
                            </div>
                            <div>
                                <h3 class="modal-name" id="register-modal-title">{{ $registerTab === 'new' ? 'Register First-Time Donor' : 'Record Returning Donor Donation' }}</h3>
                            </div>
                        </div>
                        <div class="modal-tab-switch">
                            <button type="button" class="modal-tab-btn {{ $registerTab === 'new' ? 'active' : '' }}" data-register-tab="new">
                                <i class="ti ti-user-plus"></i> New / First-Time Donor
                            </button>
                            <button type="button" class="modal-tab-btn {{ $registerTab === 'returning' ? 'active' : '' }}" data-register-tab="returning">
                                <i class="ti ti-refresh"></i> Returning Donor
                            </button>
                        </div>

                        <form class="register-form" method="POST" action="{{ route('donors.store') }}" id="register-new-panel" @if($registerTab !== 'new') hidden @endif>
                            @csrf
                            <input type="hidden" name="form" value="register-donor">
                            <input type="hidden" name="register_tab" value="new">
                            @php
                                $regStatus = old('status', 'passed');
                                $deferralReasons = ['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason'];
                                $regReason = old('reason_for_deferral');
                                $regMbd = \App\Support\DonationScreening::splitMbdDate(old('mbd_date'));
                                if (old('mbd_type')) {
                                    $regMbd['type'] = old('mbd_type');
                                }
                                if (old('mbd_on')) {
                                    $regMbd['date'] = old('mbd_on');
                                }
                                $regProvince = old('province', 'Dinagat Islands');
                            @endphp
                            <div class="register-sections">
                                <section class="form-section">
                                    <h4 class="form-section-title">Name &amp; Contact</h4>
                                    <div class="form-grid">
                                        <div class="form-field">
                                            <label for="regLastName">Last Name / Apilyedo *</label>
                                            <input id="regLastName" name="last_name" value="{{ old('last_name') }}" type="text" placeholder="Last name" data-title-case required>
                                        </div>
                                        <div class="form-field">
                                            <label for="regFullName">First Name *</label>
                                            <input id="regFullName" name="first_name" value="{{ old('first_name') }}" type="text" placeholder="First name" data-title-case required>
                                        </div>
                                        <div class="form-field">
                                            <label for="regMiddleName">Middle Name</label>
                                            <input id="regMiddleName" name="middle_name" value="{{ old('middle_name') }}" type="text" placeholder="Middle name" data-title-case>
                                        </div>
                                        <div class="form-field">
                                            <label for="regSuffix">Suffix / Title</label>
                                            <select id="regSuffix" name="suffix">
                                                <option value="" @selected(old('suffix', '') === '')>None</option>
                                                @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV'] as $suffixOption)
                                                    <option value="{{ $suffixOption }}" @selected(old('suffix') === $suffixOption)>{{ $suffixOption }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-field">
                                            <label for="regContact">Contact #</label>
                                            <input id="regContact" name="contact" value="{{ old('contact') }}" type="tel" required>
                                        </div>
                                    </div>
                                </section>
                                <section class="form-section">
                                    <h4 class="form-section-title">Event &amp; Personal Info</h4>
                            <div class="form-grid">
                                        <div class="form-field">
                                            <label for="regMbdOn">MBD / Date *</label>
                                            <div class="mbd-control">
                                                <select id="regMbdType" name="mbd_type" data-mbd-type aria-label="MBD or WI" required>
                                                    <option value="MBD" @selected($regMbd['type'] === 'MBD')>MBD</option>
                                                    <option value="WI" @selected($regMbd['type'] === 'WI')>WI</option>
                                                </select>
                                                <input id="regMbdOn" name="mbd_on" value="{{ $regMbd['date'] }}" type="date" data-mbd-on required>
                                            </div>
                                            <input id="regMbdDate" name="mbd_date" value="{{ old('mbd_date') }}" type="hidden" data-mbd-value>
                                        </div>
                                        <div class="form-field">
                                            <label for="regBirthdate">Birthdate</label>
                                            <input id="regBirthdate" name="birthdate" value="{{ old('birthdate') }}" type="date" data-birthdate>
                                        </div>
                                        <div class="form-field">
                                            <label for="regAge">Age</label>
                                            <input id="regAge" name="age" value="{{ old('age') }}" type="number" min="16" max="70" data-age required>
                                        </div>
                                        <div class="form-field">
                                            <label for="regGender">Sex</label>
                                            <select id="regGender" name="gender" required>
                                                <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Select Sex</option>
                                                <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                                                <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                                            </select>
                                </div>
                                <div class="form-field">
                                    <label for="regBloodType">Blood Type</label>
                                    <select id="regBloodType" name="blood_type" required>
                                        <option value="" disabled {{ old('blood_type') ? '' : 'selected' }}>Select Blood Type</option>
                                        @foreach ($bloodTypes as $type)
                                            <option value="{{ $type }}" @selected(old('blood_type') === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                </div>
                                </section>
                                <section class="form-section">
                                    <h4 class="form-section-title">Address</h4>
                                    <div class="form-grid" data-location-fields data-location-required>
                                <div class="form-field">
                                    <label for="regProvince">Province *</label>
                                            <select id="regProvince" name="province" data-province required>
                                                <option value="">Select province</option>
                                                @foreach ($provinces as $provinceOption)
                                                    <option value="{{ $provinceOption }}" @selected($regProvince === $provinceOption)>{{ $provinceOption }}</option>
                                                @endforeach
                                            </select>
                                </div>
                                <div class="form-field">
                                    <label for="regMunicipality">Municipality / City *</label>
                                            <select id="regMunicipality" name="municipality" data-municipality data-selected="{{ old('municipality') }}" required>
                                                <option value="">Select municipality</option>
                                            </select>
                                </div>
                                <div class="form-field">
                                    <label for="regBarangay">Barangay *</label>
                                            <select id="regBarangay" data-barangay data-selected="{{ old('barangay') }}" required>
                                                <option value="">Select barangay</option>
                                            </select>
                                            <input id="regBarangayText" data-barangay-text value="{{ old('barangay') }}" type="text" data-title-case placeholder="Barangay" hidden>
                                        </div>
                                    </div>
                                </section>
                                <section class="form-section">
                                    <h4 class="form-section-title">Donation Outcome</h4>
                                    <div class="form-field">
                                        <span class="form-section-title">Donor Status</span>
                                        <div class="status-toggle">
                                            <label for="regStatusPassed">
                                                <input id="regStatusPassed" name="status" value="passed" type="radio" @checked($regStatus === 'passed')>
                                                PASSED
                                            </label>
                                            <label for="regStatusDeferred">
                                                <input id="regStatusDeferred" name="status" value="deferred" type="radio" @checked($regStatus === 'deferred')>
                                                DEFERRED
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-grid" data-outcome="passed" @if($regStatus !== 'passed') hidden @endif>
                                        <div class="form-field">
                                            <label for="regSerialNumber">Serial #</label>
                                            <input id="regSerialNumber" name="serial_number" value="{{ old('serial_number') }}" type="text" placeholder="Blood bag barcode">
                                </div>
                                <div class="form-field">
                                            <label for="regReservedForPx">Reserved for Patient (Optional)</label>
                                            <input id="regReservedForPx" name="reserved_for_px" value="{{ old('reserved_for_px') }}" type="text" data-title-case>
                                        </div>
                                </div>
                                    <div class="form-grid" data-outcome="deferred" @if($regStatus !== 'deferred') hidden @endif>
                                <div class="form-field">
                                            <label for="regDeferralReason">Reason for Deferral</label>
                                            <select id="regDeferralReason" name="reason_for_deferral">
                                                <option value="" disabled {{ $regReason ? '' : 'selected' }}>Select reason</option>
                                                @foreach ($deferralReasons as $reasonOption)
                                                    <option value="{{ $reasonOption }}" @selected($regReason === $reasonOption)>{{ $reasonOption }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-field" data-custom-reason @if($regReason !== 'Custom Reason') hidden @endif>
                                            <label for="regDeferralCustom">Custom Reason</label>
                                            <input id="regDeferralCustom" name="reason_for_deferral_custom" value="{{ old('reason_for_deferral_custom') }}" type="text" data-title-case>
                                        </div>
                                </div>
                                </section>
                            </div>
                            @if ($errors->any() && old('form') === 'register-donor')
                                <p class="form-error">{{ $errors->first() }}</p>
                            @endif
                            <div class="modal-actions">
                                <a class="btn-cancel" href="{{ $closeDonorUrl }}">Cancel</a>
                                <button type="submit" class="btn-primary">Save Donor Record</button>
                            </div>
                        </form>
                        <script>
                            (function () {
                                const form = document.getElementById('register-new-panel');
                                if (!form) return;
                                const passed = form.querySelector('[data-outcome="passed"]');
                                const deferred = form.querySelector('[data-outcome="deferred"]');
                                const reason = document.getElementById('regDeferralReason');
                                const custom = form.querySelector('[data-custom-reason]');
                                const sync = () => {
                                    const status = form.querySelector('input[name="status"]:checked')?.value || 'passed';
                                    if (passed) passed.hidden = status !== 'passed';
                                    if (deferred) deferred.hidden = status !== 'deferred';
                                    if (custom && reason) custom.hidden = reason.value !== 'Custom Reason';
                                };
                                form.querySelectorAll('input[name="status"]').forEach((input) => {
                                    input.addEventListener('change', sync);
                                });
                                reason?.addEventListener('change', sync);
                                sync();
                            })();
                        </script>

                        <div class="returning-modal-body" id="register-returning-panel" @if($registerTab === 'new') hidden @endif>
                            <div class="search-input-wrap">
                                <i class="ti ti-search search-icon"></i>
                                <input id="returning-query" type="text" value="{{ old('returning_donor_name') }}" class="search-input" data-search-url="{{ route('blood-collection', ['lookup' => 'returning']) }}" data-store-base="{{ url('/dashboard/blood-collection/donors') }}" data-mode="{{ $mode }}" data-today="{{ now()->toDateString() }}" placeholder="Search by donor name" autocomplete="off" aria-describedby="returning-hint">
                            </div>
                            <p class="returning-search-status" id="returning-hint" role="status" aria-live="polite">Type one letter to find an existing donor.</p>
                            <div id="returning-results" class="returning-results-wrap" @unless($returningDonor) hidden @endunless>
                                @if ($returningDonor)
                                    @php $donor = $returningDonor; $showDonationForm = true; @endphp
                                    <div class="returning-donor-item" data-name="{{ mb_strtolower(\App\Http\Controllers\DonorController::displayNameFor($donor)) }}">
                                        <div class="returning-donor-info">
                                            <div class="returning-donor-header">
                                                <strong class="donor-name-link">{{ \App\Http\Controllers\DonorController::displayNameFor($donor) }}</strong>
                                                <span class="blood-type-badge sm">{{ $donor->blood_type }}</span>
                                            </div>
                                            <span class="returning-donor-meta">
                                                {{ $donor->age }} yrs · {{ $donor->gender }} · {{ $donor->formattedAddress() }} ·
                                                <strong class="text-red">{{ $donor->donations_count }}</strong> previous donation{{ (int) $donor->donations_count === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                        <button type="button" class="btn-add-donation-sm" @if($showDonationForm) hidden @endif onclick="this.hidden = true; document.getElementById('returning-donation-{{ $donor->id }}').hidden = false;">
                                            <i class="ti ti-plus"></i> Add Donation
                                        </button>
                                        <form id="returning-donation-{{ $donor->id }}" class="add-donation-panel" method="POST" action="{{ route('donations.store', $donor) }}" @unless($showDonationForm) hidden @endunless>
                                            @csrf
                                            <input type="hidden" name="form" value="returning-donation">
                                            <input type="hidden" name="register_tab" value="returning">
                                            <input type="hidden" name="returning_donor" value="{{ $donor->id }}">
                                            <input type="hidden" name="returning_donor_name" value="{{ \App\Http\Controllers\DonorController::displayNameFor($donor) }}">
                                            <input type="hidden" name="mode" value="{{ $mode }}">
                                            @php
                                                $returnMbd = \App\Support\DonationScreening::splitMbdDate(old('mbd_date'));
                                                if (old('mbd_type')) {
                                                    $returnMbd['type'] = old('mbd_type');
                                                }
                                                $returnMbd['date'] = old('mbd_on', $returnMbd['date'] !== '' ? $returnMbd['date'] : now()->toDateString());
                                            @endphp
                                            <div class="returning-donation-fields">
                                                <div class="form-field">
                                                    <label for="returnMbdOn-{{ $donor->id }}">MBD / Date</label>
                                                    <div class="mbd-control">
                                                        <select id="returnMbdType-{{ $donor->id }}" name="mbd_type" data-mbd-type aria-label="MBD or WI" required>
                                                            <option value="MBD" @selected($returnMbd['type'] === 'MBD')>MBD</option>
                                                            <option value="WI" @selected($returnMbd['type'] === 'WI')>WI</option>
                                                        </select>
                                                        <input id="returnMbdOn-{{ $donor->id }}" name="mbd_on" value="{{ $returnMbd['date'] }}" type="date" data-mbd-on required>
                                                    </div>
                                                    <input name="mbd_date" value="{{ old('mbd_date') }}" type="hidden" data-mbd-value>
                                                </div>
                                                <div class="form-actions-inline">
                                                    <button type="submit" class="btn-submit-sm">Save</button>
                                                </div>
                                            </div>
                                            @if ($showDonationForm && $errors->any())
                                                <p class="form-error">{{ $errors->first() }}</p>
                                            @endif
                                        </form>
                                    </div>
                                @endif
                            </div>
                            <div class="no-donor-prompt" id="returning-empty" hidden>
                                <p>No existing donor found matching "<strong id="returning-empty-name"></strong>".</p>
                                <button type="button" class="btn-primary" id="register-from-search">
                                    <i class="ti ti-user-plus"></i> Register as New Donor
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="collection-places">@json($locationChoices)</script>
    <script>
        (function () {
            const choices = JSON.parse(document.getElementById('collection-places')?.textContent || '{}');
            const municipalitiesByProvince = choices.municipalitiesByProvince || {};
            const places = choices.places || {};
            const particles = /\b(del|de|la|le|da|di|van|von|y|st|sa|el)\b/gi;

            const titleCaseValue = (value) => value.replace(/\S+/g, (word) => {
                const lower = word.toLocaleLowerCase();
                return (lower.charAt(0).toLocaleUpperCase() + lower.slice(1)).replace(particles, (match) => match.toLocaleLowerCase());
            });

            const fillSelect = (select, values, selected, placeholder) => {
                const current = selected || '';
                const list = values.slice();
                if (current && !list.includes(current)) {
                    list.push(current);
                }
                select.replaceChildren();
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = placeholder;
                select.appendChild(blank);
                list.forEach((value) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    if (value === current) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const bindLocationGroup = (group) => {
                if (group.dataset.bound === '1') {
                    return;
                }
                const province = group.querySelector('[data-province]');
                const municipality = group.querySelector('[data-municipality]');
                const barangay = group.querySelector('[data-barangay]');
                const barangayText = group.querySelector('[data-barangay-text]');
                const required = group.hasAttribute('data-location-required');
                if (!province || !municipality || !barangay || !barangayText) {
                    return;
                }
                group.dataset.bound = '1';

                if (!province.value && municipality.dataset.selected) {
                    const match = Object.entries(municipalitiesByProvince).find(([, list]) => list.includes(municipality.dataset.selected));
                    if (match) {
                        province.value = match[0];
                    }
                }

                const syncBarangayMode = (values) => {
                    const useText = values.length === 0;
                    barangay.hidden = useText;
                    barangay.disabled = useText;
                    barangayText.hidden = !useText;
                    barangayText.disabled = !useText;
                    if (useText) {
                        barangay.removeAttribute('name');
                        barangay.required = false;
                        barangayText.name = 'barangay';
                        barangayText.required = required;
                        if (!barangayText.value && barangay.dataset.selected) {
                            barangayText.value = barangay.dataset.selected;
                        }
                    } else {
                        barangayText.removeAttribute('name');
                        barangayText.required = false;
                        barangay.name = 'barangay';
                        barangay.required = required;
                    }
                };

                const syncBarangays = () => {
                    const values = places[municipality.value] || [];
                    const selected = barangay.value || barangay.dataset.selected || '';
                    fillSelect(barangay, values, selected, 'Select barangay');
                    syncBarangayMode(values);
                };

                const syncMunicipalities = () => {
                    const values = municipalitiesByProvince[province.value] || [];
                    fillSelect(municipality, values, municipality.dataset.selected || '', 'Select municipality');
                    municipality.disabled = province.value === '';
                    syncBarangays();
                };

                province.addEventListener('change', () => {
                    municipality.dataset.selected = '';
                    barangay.dataset.selected = '';
                    barangayText.value = '';
                    syncMunicipalities();
                });
                municipality.addEventListener('change', () => {
                    barangay.dataset.selected = '';
                    barangayText.value = '';
                    syncBarangays();
                });
                syncMunicipalities();
            };

            const bindMbdForm = (form) => {
                if (form.dataset.mbdBound === '1' || !form.querySelector('[data-mbd-type]')) {
                    return;
                }
                form.dataset.mbdBound = '1';
                const syncMbd = () => {
                    const type = form.querySelector('[data-mbd-type]');
                    const date = form.querySelector('[data-mbd-on]');
                    const hidden = form.querySelector('[data-mbd-value]');
                    if (!type || !date || !hidden || !date.value) {
                        return;
                    }
                    const [year, month, day] = date.value.split('-');
                    if (!year || !month || !day) {
                        return;
                    }
                    hidden.value = `${type.value} ${month}/${day}/${year}`;
                };
                form.querySelector('[data-mbd-type]')?.addEventListener('change', syncMbd);
                form.querySelector('[data-mbd-on]')?.addEventListener('change', syncMbd);
                form.addEventListener('submit', () => {
                    form.querySelectorAll('[data-title-case]').forEach((input) => {
                        if (!input.disabled) {
                            input.value = titleCaseValue(input.value.trim());
                        }
                    });
                    syncMbd();
                });
                syncMbd();
            };

            window.bindCollectionFields = (root) => {
                (root || document).querySelectorAll('[data-location-fields]').forEach(bindLocationGroup);
                (root || document).querySelectorAll('form').forEach(bindMbdForm);
            };
            window.bindCollectionFields(document);
        })();
    </script>
@endsection
