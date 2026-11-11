@extends('layouts.app')

@section('title', 'CRH Blood Bank — Blood Consumption')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/blood-consumption.css') }}">
    <style>
        .module-page--form-page .consumption-module > :not(.page-panel) {
            display: none !important;
        }
        .module-page--form-page .page-panel {
            position: static;
            inset: auto;
            z-index: auto;
            display: block;
            width: 100%;
            padding: 0;
            background: transparent;
        }
        .module-page--form-page .page-panel[hidden] {
            display: none !important;
        }
        .module-page--form-page .page-panel .modal-card {
            width: 100%;
            max-width: none !important;
            max-height: none;
            overflow: visible;
            border: 1px solid #f1d7d7;
            box-shadow: none;
        }
        .cursor-pointer { cursor: pointer; }
        .transition-colors { transition: background-color 150ms, color 150ms; }
        .hover\:bg-slate-50:hover { background: #f8fafc; }
        .consumption-trash {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            line-height: 0;
            cursor: pointer;
        }
        .p-1\.5 { padding: 0.375rem; }
        .text-slate-400 { color: #94a3b8; }
        .hover\:text-red-600:hover { color: #dc2626; }
        .hover\:bg-red-50:hover { background: #fef2f2; }
        .rounded-md { border-radius: 0.375rem; }
        .transition { transition: color 150ms, background-color 150ms; }
        .consumption-date-cell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .consumption-detail-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .consumption-detail-card {
            width: min(100%, 680px);
            max-width: none;
            max-height: min(86vh, 760px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
            border: 1px solid #eadfe2;
            border-top: 4px solid #800020;
            border-radius: 14px;
            box-shadow: 0 24px 70px rgba(31, 15, 20, 0.24);
        }
        .consumption-visits {
            display: grid;
            gap: 12px;
            min-height: 0;
            padding: 1rem 1.35rem 1.35rem;
            overflow: auto;
            background: #f7f5f6;
        }
        .consumption-visit {
            padding: 14px;
            background: #fff;
            border: 1px solid #e9e1e3;
            border-left: 3px solid #990000;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(40, 20, 25, 0.04);
        }
        .consumption-visit-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .consumption-visit-date {
            color: #3c242b;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .consumption-visit-details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin: 0;
        }
        .consumption-visit-detail {
            min-width: 0;
            padding: 8px 10px;
            border: 1px solid #f0edef;
            border-radius: 7px;
            background: #faf9fa;
        }
        .consumption-visit-detail--wide {
            grid-column: 1 / -1;
        }
        .consumption-visit-detail dt {
            margin-bottom: 3px;
            color: #817478;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .consumption-visit-detail dd {
            margin: 0;
            color: #393033;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
        .consumption-detail-header {
            flex-shrink: 0;
            margin: 0;
            padding: 1.25rem 1.35rem;
            border-bottom: 1px solid #eee8e9;
        }
        .consumption-detail-heading {
            min-width: 0;
        }
        .consumption-detail-card .modal-header {
            margin-bottom: 0;
            padding-bottom: 1rem;
        }
        .consumption-detail-card .panel-title {
            margin-top: 2px;
            color: #302327;
            font-size: 1.1rem;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .consumption-detail-eyebrow {
            margin: 0;
            color: #990000;
            font-size: 0.67rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .consumption-detail-sub {
            display: inline-flex;
            align-items: center;
            margin: 8px 0 0;
            padding: 4px 9px;
            border-radius: 999px;
            background: #f8edef;
            color: #7d2738;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .consumption-detail-close {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #f7f4f5;
        }
        .consumption-detail-close:hover {
            background: #f2e7e9;
            color: #800020;
        }
        .consumption-trash {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            border: 1px solid #eee7e9;
            border-radius: 7px;
            background: #fff;
            color: #8b7278;
        }
        .consumption-trash:hover {
            border-color: #f0c9d0;
            background: #fff4f5;
            color: #990000;
        }
        .consumption-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-delete-all {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 34px;
            padding: 0 12px;
            border: 1px solid #f0c9d0;
            border-radius: 7px;
            background: #fff4f5;
            color: #990000;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-delete-all:hover {
            background: #fee2e2;
        }
        .btn-delete-all svg {
            width: 14px;
            height: 14px;
        }
        .patient-name-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
        }
        .patient-name-heading .btn-delete-all {
            flex: 0 0 auto;
        }
        .consumption-type-rows {
            display: grid;
            gap: 6px;
        }
        .consumption-type-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 6px 10px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
        }
        .consumption-type-row strong {
            color: #1a202c;
            font-size: 0.85rem;
        }
        .consumption-total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .consumption-total-row strong {
            color: #990000;
            font-size: 1rem;
        }
        .consumption-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            background: #FDECEC;
            color: #990000;
            font-size: 0.75rem;
            font-weight: 700;
        }
        @media (max-width: 640px) {
            .modal-overlay {
                padding: 12px;
            }
            .consumption-detail-card {
                max-height: calc(100dvh - 24px);
                border-radius: 12px;
            }
            .consumption-detail-header {
                padding: 1rem;
            }
            .consumption-visits {
                gap: 10px;
                padding: 0.75rem;
            }
            .consumption-visit {
                padding: 10px;
            }
            .consumption-visit-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
            }
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
        .module-page__header {
            margin-bottom: 0.35rem;
            flex-shrink: 0;
        }
        .module-page__header h2 {
            font-size: 1.25rem;
            margin-bottom: 0;
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
        .consumption-module {
            gap: 0.45rem;
            width: 100%;
            max-width: 100%;
        }
        .consumption-module,
        .consumption-module > .panel,
        .consumption-search {
            display: flex;
            flex-direction: column;
            overflow: visible;
            width: 100%;
        }
        #consumption-results {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 100%;
        }
        #consumption-results > .summary-grid,
        #consumption-results > .search-input-wrap,
        #consumption-results > .results-count {
            flex: 0 0 auto;
        }
        .panel {
            gap: 0.45rem;
        }
        .flash-banner {
            margin: 0 0 0.45rem;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .panel-header {
            margin: 0;
            padding-bottom: 0.35rem;
            flex-shrink: 0;
        }
        .panel-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--color-red-primary);
        }
        .panel-header .btn-add {
            margin-left: auto;
        }
        .panel-title {
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-add,
        .reset-btn,
        .filter-select,
        .search-input {
            font-size: 0.75rem;
        }
        .search-input,
        .filter-select,
        .reset-btn {
            height: 28px;
            padding-top: 2px;
            padding-bottom: 2px;
            box-sizing: border-box;
        }
        .search-input-wrap {
            position: relative;
            flex: 0 0 auto !important;
            width: 100%;
            min-width: 0;
            min-height: 0;
            height: auto;
            margin-top: 0;
            align-self: stretch;
        }
        .search-input-wrap .search-icon {
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .search-input-wrap .search-input {
            display: block;
            width: 100%;
        }
        .filters-bar,
        .summary-grid,
        .results-count,
        .consumption-total-table {
            flex-shrink: 0;
        }
        .results-count {
            font-size: 0.75rem;
            margin: 0;
        }
        .summary-grid {
            gap: 6px;
        }
        .summary-grid .summary-card {
            padding: 4px 6px;
        }
        .summary-grid .summary-card span {
            font-size: 0.6875rem;
            font-weight: 600;
        }
        .summary-grid .summary-card strong {
            font-size: 0.875rem;
            font-weight: 600;
        }
        .consumption-table-frame {
            display: flex;
            flex-direction: column;
            width: 100%;
            overflow: visible;
            background: transparent;
            border: 0;
        }
        .consumption-module .table-wrap {
            width: 100%;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            background: #fff;
        }
        .records-table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        .records-table th,
        .records-table td {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .records-table th {
            font-weight: 600;
        }
        .records-table td {
            font-weight: 500;
        }
        .records-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fafafa;
        }
        .records-table .blood-type-badge {
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            line-height: 1.2;
        }
        .consumption-total-table td {
            background: #fafafa;
            border-top: 2px solid #e5e5e5;
            font-weight: 600;
        }
        .summary-grid {
            grid-template-columns: repeat(8, minmax(0, 1fr));
        }
        .summary-grid .summary-card span {
            white-space: normal;
            line-height: 1.2;
        }
        .location-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
        }
        .province-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .province-tab {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
            transition: background-color 0.15s, border-color 0.15s, color 0.15s;
        }
        .province-tab.is-active {
            background: #990000;
            color: #fff;
            border-color: #990000;
        }
        .province-tab:not(.is-active):hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
    </style>
@endpush

@section('content')
    @php
        $keptFilters = array_filter([
            'q' => $search !== '' ? $search : null,
        ]);
        $listReturnUrl = route('blood-consumption', array_filter([
            'province' => $province !== '' ? $province : null,
            'municipality' => $municipality !== '' ? $municipality : null,
            'barangay' => $barangay !== '' ? $barangay : null,
            'component' => $component !== '' ? $component : null,
            'month' => $month !== '' ? $month : null,
            'year' => $year !== '' ? $year : null,
            'q' => $search !== '' ? $search : null,
        ]));
        $patientReturnUrl = $editingConsumption
            ? route('blood-consumption', array_filter([
                'details' => 1,
                'details_page' => 1,
                'patient' => $editingConsumption->patient_name,
                'province' => $province !== '' ? $province : null,
                'municipality' => $municipality !== '' ? $municipality : null,
                'barangay' => $barangay !== '' ? $barangay : null,
                'component' => $component !== '' ? $component : null,
                'month' => $month !== '' ? $month : null,
                'year' => $year !== '' ? $year : null,
                'q' => $search !== '' ? $search : null,
            ]))
            : $listReturnUrl;
    @endphp
    <div class="module-page {{ $formPage ? 'module-page--form-page' : '' }}">
        <div class="module-page__content">
            <div class="consumption-module">
                <section class="panel">
                    <div class="panel-header">
                        <h2>Consumption Records</h2>
                        <div class="consumption-actions">
                            <a class="btn-primary btn-add" href="{{ route('blood-consumption', array_filter(['add' => 1] + $keptFilters)) }}">
                                <i class="ti ti-plus"></i> Add Consumption Record
                            </a>
                        </div>
                    </div>
                    <div class="consumption-search">
                        <nav class="location-nav" aria-label="Address">
                            <div class="province-tabs" aria-label="Filter by province">
                                <a
                                    class="province-tab {{ $province === '' ? 'is-active' : '' }}"
                                    href="{{ route('blood-consumption', $keptFilters) }}"
                                    @if ($province === '') aria-current="page" @endif
                                >All</a>
                                @foreach ($provinces as $provinceOption)
                                    <a
                                        class="province-tab {{ $province === $provinceOption ? 'is-active' : '' }}"
                                        href="{{ route('blood-consumption', $keptFilters + ['province' => $provinceOption]) }}"
                                        @if ($province === $provinceOption) aria-current="page" @endif
                                    >{{ $provinceOption }}</a>
                                @endforeach
                            </div>
                        </nav>

                        <div id="consumption-results">
                        <div class="summary-grid" id="consumption-summary">
                            <div class="summary-card">
                                <span>Total Units</span>
                                <strong id="consumption-total">{{ number_format($totalUnits) }}</strong>
                            </div>
                            @foreach ($totalsByComponent as $item)
                                <div class="summary-card" data-summary-component="{{ $item['component'] }}">
                                    <span>{{ $item['component'] }}</span>
                                    <strong>{{ number_format($item['total']) }}</strong>
                                </div>
                            @endforeach
                        </div>

                        <div class="search-input-wrap">
                            <i class="ti ti-search search-icon"></i>
                            <input id="consumption-query" name="q" value="{{ $search }}" type="text" class="search-input" placeholder="Search patient name" autocomplete="off">
                        </div>

                        <p class="results-count">
                            <span id="consumption-count">{{ number_format($matchTotal) }}</span> name{{ $matchTotal === 1 ? '' : 's' }} found
                            @if ($municipality !== '') for {{ $municipality }}@if ($barangay !== ''), {{ $barangay }}@endif @endif
                            <span id="consumption-limit-note">@if ($matchTotal > $patients->count()) Showing {{ number_format($patients->count()) }} of {{ number_format($matchTotal) }}. Type a name to narrow the list.@endif</span>
                        </p>

                        <div class="consumption-table-frame">
                        <div class="table-wrap" id="consumption-name-wrap">
                            <table class="records-table" id="consumption-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="patient-name-heading">
                                                <span>Patient Name</span>
                                                @if ($recordCount > 0)
                                                    <button
                                                        type="button"
                                                        class="btn-delete-all"
                                                        data-delete-url="{{ route('consumption.destroy-all', array_filter([
                                                            'province' => $province !== '' ? $province : null,
                                                            'municipality' => $municipality !== '' ? $municipality : null,
                                                            'barangay' => $barangay !== '' ? $barangay : null,
                                                            'component' => $component !== '' ? $component : null,
                                                            'month' => $month !== '' ? $month : null,
                                                            'year' => $year !== '' ? $year : null,
                                                            'q' => $search !== '' ? $search : null,
                                                        ])) }}"
                                                        data-delete-name="All consumption records"
                                                        data-delete-title="Delete All Consumption Records"
                                                        data-delete-message="Are you sure you want to permanently delete all consumption records? This action cannot be undone."
                                                        onclick="confirmDeleteAllConsumption(this)"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                        Delete All
                                                    </button>
                                                @endif
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="consumption-name-body">
                                    @forelse ($patients as $patient)
                                        <tr class="cursor-pointer hover:bg-slate-50 transition-colors"
                                            data-patient="{{ $patient['name'] }}"
                                            onclick="openConsumptionRow(this)">
                                            <td class="consumption-patient-name">{{ $patient['name'] }}</td>
                                        </tr>
                                    @empty
                                        <tr data-empty-row>
                                            <td class="empty-state">No patients match your search.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        </div>
                        </div>
                    </div>
                </section>

                <div id="consumption-edit-modal" class="{{ $formPage ? 'page-panel' : 'modal-overlay' }}" @if ($editingConsumptionId > 0) @else hidden @endif>
                    <div class="modal-card" @unless($formPage) role="dialog" aria-modal="true" @endunless aria-labelledby="editConsumptionTitle" style="max-width: 640px;">
                        <div class="modal-header">
                            <h3 id="editConsumptionTitle" class="panel-title">Edit Consumption Record</h3>
                            <a class="modal-close" href="{{ $patientReturnUrl }}" aria-label="Back to patient history" title="Back to patient history">
                                <i class="ti ti-x"></i>
                            </a>
                        </div>
                        <form id="consumption-edit-form" method="POST" action="{{ $editingConsumption ? route('consumption.update', $editingConsumption) : '' }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="form" value="edit-consumption">
                            <input type="hidden" name="consumption_id" id="editConsumptionId" value="{{ old('consumption_id', $editingConsumptionId ?: '') }}">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="editPatientName">Patient Name</label>
                                    <input id="editPatientName" name="patient_name" value="{{ old('patient_name', $editingConsumption?->patient_name) }}" type="text" required>
                                </div>
                                <div class="form-field">
                                    <label for="editDateSubmitted">Date</label>
                                    <input id="editDateSubmitted" name="date_submitted" value="{{ old('date_submitted', $editingConsumption?->date_submitted?->format('Y-m-d')) }}" type="date" required>
                                </div>
                                <div class="form-field">
                                    <label for="editPatientAge">Age</label>
                                    <input id="editPatientAge" name="age" value="{{ old('age', $editingConsumption?->age) }}" type="number" min="0" max="120" required>
                                </div>
                                <div class="form-field">
                                    <label for="editPatientSex">Sex</label>
                                    <select id="editPatientSex" name="sex" required>
                                        <option value="M" @selected(old('sex', $editingConsumption?->sex) === 'M')>M</option>
                                        <option value="F" @selected(old('sex', $editingConsumption?->sex) === 'F')>F</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="editBloodComponent">Blood Component</label>
                                    <select id="editBloodComponent" name="blood_component" required>
                                        <option value="" @selected(old('blood_component', $editingConsumption?->blood_component) === null)>Select component</option>
                                        @foreach ($components as $name)
                                            <option value="{{ $name }}" @selected(old('blood_component', $editingConsumption?->blood_component) === $name)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="editUnits">No. of Units Crossmatched/Given</label>
                                    <input id="editUnits" name="units" value="{{ old('units', $editingConsumption?->number_of_bags) }}" type="number" min="1" step="1" required>
                                </div>
                                <div class="form-field">
                                    <label for="editProvince">Province</label>
                                    <select id="editProvince" name="province" required>
                                        <option value="">Select province</option>
                                        @foreach ($provinces as $provinceOption)
                                            <option value="{{ $provinceOption }}" @selected(old('province', $editingConsumption?->province) === $provinceOption)>{{ $provinceOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="editMunicipality">Municipality</label>
                                    <select id="editMunicipality" name="municipality" data-selected="{{ old('municipality', $editingConsumption?->municipality) }}" required>
                                        <option value="">Select municipality</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="editBarangay">Barangay</label>
                                    <select id="editBarangay" name="barangay" data-selected="{{ old('barangay', $editingConsumption?->barangay) }}">
                                        <option value="">Select barangay</option>
                                    </select>
                                </div>
                            </div>
                            @if ($errors->any() && old('form') === 'edit-consumption')
                                <p class="error-message"><i class="ti ti-x"></i> {{ $errors->first() }}</p>
                            @endif
                            <div class="form-actions">
                                <a class="btn-secondary" href="{{ $patientReturnUrl }}">Cancel</a>
                                <button type="submit" class="btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="consumption-detail" class="modal-overlay" hidden>
                    <div class="modal-card consumption-detail-card" role="dialog" aria-modal="true" aria-labelledby="consumption-detail-title">
                        <div class="modal-header consumption-detail-header">
                            <div class="consumption-detail-heading">
                                <p class="consumption-detail-eyebrow">Patient consumption history</p>
                                <h3 id="consumption-detail-title" class="panel-title">Consumption Record</h3>
                                <p class="consumption-detail-sub" id="detail-count"></p>
                            </div>
                            <button type="button" class="modal-close consumption-detail-close" data-modal-close="consumption-detail" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="consumption-visits" id="detail-visits"></div>
                    </div>
                </div>

                <div id="consumption-modal" class="{{ $formPage ? 'page-panel' : 'modal-overlay' }}" @unless($openModal) hidden @endunless>
                    <div class="modal-card" @unless($formPage) role="dialog" aria-modal="true" @endunless aria-labelledby="addRecordModalTitle" style="max-width: 640px;">
                        <div class="modal-header">
                            <h3 id="addRecordModalTitle" class="panel-title">Add Consumption Record</h3>
                            <a class="modal-close" href="{{ $listReturnUrl }}" aria-label="Back to consumption records" title="Back to consumption records">
                                <i class="ti ti-x"></i>
                            </a>
                        </div>
                        <form class="consumption-form" method="POST" action="{{ route('consumption.store') }}">
                            @csrf
                            <input type="hidden" name="form" value="consumption">
                            <div class="patient-mode-toggle" role="group" aria-label="Patient type">
                                <label class="patient-mode-option">
                                    <input type="radio" name="patient_mode" value="new" @checked(old('patient_mode', 'new') === 'new')>
                                    <span>New patient</span>
                                </label>
                                <label class="patient-mode-option">
                                    <input type="radio" name="patient_mode" value="returning" @checked(old('patient_mode') === 'returning')>
                                    <span>Returning patient</span>
                                </label>
                            </div>
                            <div class="returning-patient-fields" data-returning-fields hidden>
                                <div class="form-field">
                                    <label for="returningPatientName">Find existing patient</label>
                                    <input
                                        id="returningPatientName"
                                        name="returning_patient_name"
                                        value="{{ old('returning_patient_name') }}"
                                        type="search"
                                        placeholder="Type a patient name"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="returningPatientOptions"
                                        aria-expanded="false"
                                        disabled
                                    >
                                    <div id="returningPatientOptions" class="patient-suggestions" role="listbox" hidden></div>
                                    <small id="returningPatientHint">Existing patient details are reused. The date will be set to today.</small>
                                </div>
                            </div>
                            <div class="form-grid" data-new-patient-fields>
                                <div class="form-field">
                                    <label for="dateSubmitted">Date</label>
                                    <input id="dateSubmitted" name="date_submitted" value="{{ old('date_submitted', now()->toDateString()) }}" type="date" required>
                                </div>
                                <div class="form-field">
                                    <label for="patientLastName">Last Name / Apilyedo</label>
                                    <input id="patientLastName" name="last_name" value="{{ old('last_name') }}" type="text" placeholder="Last name" required>
                                </div>
                                <div class="form-field">
                                    <label for="patientFirstName">First Name</label>
                                    <input id="patientFirstName" name="first_name" value="{{ old('first_name') }}" type="text" placeholder="First name" required>
                                </div>
                                <div class="form-field">
                                    <label for="patientMiddleName">Middle Name</label>
                                    <input id="patientMiddleName" name="middle_name" value="{{ old('middle_name') }}" type="text" placeholder="Middle name">
                                </div>
                                <div class="form-field">
                                    <label for="patientSuffix">Suffix / Title</label>
                                    <select id="patientSuffix" name="suffix">
                                        <option value="" @selected(old('suffix', '') === '')>None</option>
                                        @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV'] as $suffixOption)
                                            <option value="{{ $suffixOption }}" @selected(old('suffix') === $suffixOption)>{{ $suffixOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="patientAge">Age</label>
                                    <input id="patientAge" name="age" value="{{ old('age') }}" type="number" min="0" max="120" required>
                                </div>
                                <div class="form-field">
                                    <label for="patientSex">Sex</label>
                                    <select id="patientSex" name="sex" required>
                                        <option value="" @selected(old('sex') === null)>Select</option>
                                        <option value="M" @selected(old('sex') === 'M')>M</option>
                                        <option value="F" @selected(old('sex') === 'F')>F</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="province">Province</label>
                                    <select id="province" name="province" required>
                                        <option value="">Select province</option>
                                        @foreach ($provinces as $provinceOption)
                                            <option value="{{ $provinceOption }}" @selected(old('province') === $provinceOption)>{{ $provinceOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="municipality">Municipality</label>
                                    <select id="municipality" name="municipality" data-selected="{{ old('municipality') }}" required>
                                        <option value="">Select province first</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="barangay">Barangay</label>
                                    <select id="barangay" name="barangay" data-selected="{{ old('barangay') }}">
                                        <option value="">Select municipality first</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="bloodComponent">Blood Component</label>
                                    <select id="bloodComponent" name="blood_component" required>
                                        <option value="">Select component</option>
                                        @foreach ($components as $name)
                                            <option value="{{ $name }}" @selected(old('blood_component') === $name)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="units">No. of Units Crossmatched/Given</label>
                                    <input id="units" name="units" value="{{ old('units') }}" type="number" min="1" step="1" required>
                                </div>
                            </div>
                            @if ($errors->any() && old('form') === 'consumption')
                                <p class="error-message"><i class="ti ti-x"></i> {{ $errors->first() }}</p>
                            @endif
                            <div class="form-actions">
                                <a class="btn-secondary" href="{{ $listReturnUrl }}">Cancel</a>
                                <button type="submit" class="btn-primary">Save Record</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const locationChoices = @json($locationChoices);
            const bindLocationSelects = (provinceId, municipalityId, barangayId) => {
                const province = document.getElementById(provinceId);
                const municipality = document.getElementById(municipalityId);
                const barangay = document.getElementById(barangayId);
                if (!province || !municipality || !barangay) return () => {};

                const fill = (select, values, placeholder, selected) => {
                    const names = values.slice();
                    if (selected && !names.includes(selected)) names.push(selected);
                    select.replaceChildren();
                    const empty = document.createElement('option');
                    empty.value = '';
                    empty.textContent = placeholder;
                    select.appendChild(empty);
                    names.forEach((name) => {
                        const option = document.createElement('option');
                        option.value = name;
                        option.textContent = name;
                        option.selected = name === selected;
                        select.appendChild(option);
                    });
                };

                const sync = (reset) => {
                    const selectedMunicipality = reset ? '' : municipality.value;
                    const municipalities = locationChoices.municipalitiesByProvince?.[province.value] ?? [];
                    fill(
                        municipality,
                        municipalities,
                        province.value ? 'Select municipality' : 'Select province first',
                        selectedMunicipality
                    );
                    municipality.disabled = !province.value;
                    municipality.required = Boolean(province.value);

                    const selectedBarangay = reset ? '' : barangay.value;
                    const barangays = locationChoices.places?.[municipality.value] ?? [];
                    const barangayPlaceholder = !municipality.value
                        ? 'Select municipality first'
                        : (barangays.length > 0 ? 'Select barangay' : 'No barangays available');
                    fill(barangay, barangays, barangayPlaceholder, selectedBarangay);
                    barangay.disabled = !municipality.value || barangays.length === 0;
                };

                let ready = false;
                province.addEventListener('change', () => sync(true));
                municipality.addEventListener('change', () => {
                    if (!ready) return;
                    const barangays = locationChoices.places?.[municipality.value] ?? [];
                    fill(
                        barangay,
                        barangays,
                        barangays.length > 0 ? 'Select barangay' : 'No barangays available',
                        ''
                    );
                    barangay.disabled = barangays.length === 0;
                });
                const initialMunicipality = municipality.dataset.selected || '';
                const initialBarangay = barangay.dataset.selected || '';
                fill(
                    municipality,
                    locationChoices.municipalitiesByProvince?.[province.value] ?? [],
                    province.value ? 'Select municipality' : 'Select province first',
                    initialMunicipality
                );
                municipality.disabled = !province.value;
                municipality.required = Boolean(province.value);
                const initialBarangays = locationChoices.places?.[municipality.value] ?? [];
                fill(
                    barangay,
                    initialBarangays,
                    !municipality.value
                        ? 'Select municipality first'
                        : (initialBarangays.length > 0 ? 'Select barangay' : 'No barangays available'),
                    initialBarangay
                );
                barangay.disabled = !municipality.value || initialBarangays.length === 0;
                ready = true;

                return sync;
            };

            window.syncAddConsumptionLocation = bindLocationSelects('province', 'municipality', 'barangay');
            bindLocationSelects('editProvince', 'editMunicipality', 'editBarangay');
        })();

        (function () {
            const form = document.querySelector('#consumption-modal .consumption-form');
            const returningFields = form?.querySelector('[data-returning-fields]');
            const newFields = form?.querySelector('[data-new-patient-fields]');
            const patientInput = form?.querySelector('#returningPatientName');
            const options = form?.querySelector('#returningPatientOptions');
            const hint = form?.querySelector('#returningPatientHint');
            const modeInputs = form?.querySelectorAll('input[name="patient_mode"]');
            if (!form || !returningFields || !newFields || !patientInput || !options || !modeInputs) return;

            const updateMode = () => {
                const isReturning = form.querySelector('input[name="patient_mode"]:checked')?.value === 'returning';
                returningFields.hidden = !isReturning;
                patientInput.disabled = !isReturning;
                patientInput.required = isReturning;
                newFields.hidden = isReturning;
                newFields.querySelectorAll('input, select').forEach((field) => {
                    field.disabled = isReturning;
                });
                if (!isReturning && window.syncAddConsumptionLocation) {
                    window.syncAddConsumptionLocation(false);
                }
                const heading = document.getElementById('addRecordModalTitle');
                if (heading) heading.textContent = isReturning ? 'Add Returning Patient Record' : 'Add Consumption Record';
            };

            modeInputs.forEach((input) => input.addEventListener('change', updateMode));
            form.addEventListener('reset', () => window.setTimeout(updateMode, 0));
            updateMode();

            let searchController;
            let searchTimer;
            patientInput.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchController?.abort();
                options.replaceChildren();
                options.hidden = true;
                patientInput.setAttribute('aria-expanded', 'false');
                if (hint) hint.textContent = 'Existing patient details are reused. The date will be set to today.';
                const query = patientInput.value.trim();
                if (query.length < 1) return;

                if (hint) hint.textContent = 'Searching existing patients…';
                searchTimer = window.setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.search = '';
                    url.searchParams.set('returning_patients', '1');
                    url.searchParams.set('q', query);
                    searchController = new AbortController();
                    fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        signal: searchController.signal,
                    })
                        .then(async (response) => {
                            const result = await response.json();
                            if (!response.ok) throw new Error(result.message || 'Unable to find returning patients.');
                            return result;
                        })
                        .then((result) => {
                            options.replaceChildren();
                            const patients = result.patients || [];
                            patients.forEach((name) => {
                                const option = document.createElement('button');
                                option.type = 'button';
                                option.className = 'patient-suggestion';
                                option.setAttribute('role', 'option');
                                option.innerHTML = '<span class="patient-suggestion-name"></span><span class="patient-suggestion-action">Select patient</span>';
                                option.querySelector('.patient-suggestion-name').textContent = name;
                                option.addEventListener('click', () => {
                                    patientInput.value = name;
                                    options.replaceChildren();
                                    options.hidden = true;
                                    patientInput.setAttribute('aria-expanded', 'false');
                                    if (hint) hint.textContent = `Selected ${name}. Existing patient details will be reused.`;
                                });
                                options.appendChild(option);
                            });
                            options.hidden = patients.length === 0;
                            patientInput.setAttribute('aria-expanded', String(patients.length > 0));
                            if (hint) {
                                hint.textContent = patients.length === 0
                                    ? 'No matching returning patient was found.'
                                    : `${patients.length} matching patient${patients.length === 1 ? '' : 's'} found. Select a patient to continue.`;
                            }
                        })
                        .catch((error) => {
                            if (error.name !== 'AbortError') {
                                options.replaceChildren();
                                options.hidden = true;
                                patientInput.setAttribute('aria-expanded', 'false');
                                if (hint) hint.textContent = error.message || 'Unable to search patients. Please try again.';
                            }
                        });
                }, 250);
            });
        })();

        let consumptionHistoryRequest;

        function openConsumptionRow(row) {
            const url = new URL(window.location.href);
            url.searchParams.delete('search_json');
            url.searchParams.set('details', '1');
            url.searchParams.set('details_page', '1');
            url.searchParams.set('patient', row.dataset.patient || '');
            window.location.href = url.toString();
        }

        function confirmDeleteConsumption(id) {
            const button = document.querySelector('[data-consumption-id="' + id + '"]');
            const deleteModal = document.getElementById('delete-confirm-modal');
            const deleteForm = document.getElementById('delete-confirm-form');
            const deleteName = document.getElementById('delete-confirm-name');
            if (!button || !deleteModal || !deleteForm) return;
            deleteForm.action = button.dataset.deleteUrl;
            if (deleteName) deleteName.textContent = button.dataset.deleteName || '';
            const title = document.getElementById('deleteConfirmTitle');
            const message = document.querySelector('#delete-confirm-modal .delete-confirm-message');
            if (title) title.textContent = 'Delete Record';
            if (message) message.textContent = 'Are you sure you want to delete this record? This action cannot be undone.';
            deleteModal.removeAttribute('hidden');
        }

        function openConsumptionEditor(visit) {
            const url = new URL(window.location.href);
            url.searchParams.delete('details');
            url.searchParams.delete('details_page');
            url.searchParams.set('edit', String(visit.id));
            window.location.href = url.toString();
        }

        function confirmDeleteAllConsumption(button) {
            const deleteModal = document.getElementById('delete-confirm-modal');
            const deleteForm = document.getElementById('delete-confirm-form');
            const deleteName = document.getElementById('delete-confirm-name');
            if (!deleteModal || !deleteForm) return;
            deleteForm.action = button.dataset.deleteUrl;
            if (deleteName) deleteName.textContent = button.dataset.deleteName || '';
            const title = document.getElementById('deleteConfirmTitle');
            const message = document.querySelector('#delete-confirm-modal .delete-confirm-message');
            if (title) title.textContent = button.dataset.deleteTitle || 'Delete Record';
            if (message) message.textContent = button.dataset.deleteMessage || 'Are you sure you want to delete this record? This action cannot be undone.';
            deleteModal.removeAttribute('hidden');
        }

        (function () {
            const input = document.getElementById('consumption-query');
            const tbody = document.getElementById('consumption-name-body');
            const wrap = document.getElementById('consumption-name-wrap');
            const count = document.getElementById('consumption-count');
            const note = document.getElementById('consumption-limit-note');
            if (!input || !tbody) return;

            let controller;
            const run = () => {
                const url = new URL(window.location.href);
                const term = input.value.trim();
                url.searchParams.delete('search_json');
                if (term !== '') {
                    url.searchParams.set('q', term);
                } else {
                    url.searchParams.delete('q');
                }
                const requestUrl = new URL(url.toString());
                requestUrl.searchParams.set('search_json', '1');
                controller?.abort();
                controller = new AbortController();
                const current = controller;
                wrap?.classList.add('is-loading');
                fetch(requestUrl.toString(), {
                    headers: { 'Accept': 'application/json' },
                    signal: current.signal,
                })
                    .then(async (response) => {
                        const result = await response.json();
                        if (!response.ok) {
                            throw new Error(result.message || 'Unable to search consumption records.');
                        }

                        return result;
                    })
                    .then((result) => {
                        tbody.replaceChildren();
                        result.names.forEach((name) => {
                            const row = document.createElement('tr');
                            row.className = 'cursor-pointer hover:bg-slate-50 transition-colors';
                            row.dataset.patient = name;
                            row.addEventListener('click', () => openConsumptionRow(row));
                            const cell = document.createElement('td');
                            cell.className = 'consumption-patient-name';
                            cell.textContent = name;
                            row.appendChild(cell);
                            tbody.appendChild(row);
                        });
                        if (result.names.length === 0) {
                            const row = document.createElement('tr');
                            row.dataset.emptyRow = '';
                            const cell = document.createElement('td');
                            cell.className = 'empty-state';
                            cell.textContent = 'No patients match your search.';
                            row.appendChild(cell);
                            tbody.appendChild(row);
                        }
                        if (count) count.textContent = Number(result.matchTotal).toLocaleString();
                        if (note) note.textContent = result.limitNote;
                        history.replaceState(null, '', url.toString());
                    })
                    .catch((error) => {
                        if (error.name === 'AbortError') return;
                        tbody.replaceChildren();
                        const row = document.createElement('tr');
                        const cell = document.createElement('td');
                        cell.className = 'empty-state';
                        cell.textContent = error.message || 'Unable to search consumption records. Please try again.';
                        row.appendChild(cell);
                        tbody.appendChild(row);
                    })
                    .finally(() => {
                        if (controller !== current) return;
                        wrap?.classList.remove('is-loading');
                    });
            };

            input.addEventListener('input', () => {
                window.clearTimeout(window.consumptionSearchTimer);
                window.consumptionSearchTimer = window.setTimeout(run, 300);
            });
        })();
    </script>
@endsection
