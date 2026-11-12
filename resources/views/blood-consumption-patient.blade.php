@extends('layouts.app')

@section('title', 'CRH Blood Bank — Patient Consumption History')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/blood-consumption.css') }}">
    <style>
        .patient-history-page {
            display: grid;
            gap: 1rem;
        }
        .patient-history-header,
        .patient-history-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .patient-history-title {
            margin: 0;
            color: #990000;
        }
        .patient-history-note {
            margin: 0.25rem 0 0;
            color: #64748b;
        }
        .patient-history-record {
            padding: 1rem;
            border: 1px solid #f1d7d7;
            border-radius: 10px;
            background: #fff;
        }
        .patient-history-record h3 {
            margin: 0;
            color: #990000;
            font-size: 1rem;
        }
        .patient-history-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.8rem;
            margin: 0.9rem 0 0;
        }
        .patient-history-details div {
            min-width: 0;
        }
        .patient-history-details dt {
            color: #64748b;
            font-size: 0.75rem;
        }
        .patient-history-details dd {
            margin: 0.15rem 0 0;
            color: #1f2937;
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .patient-history-actions {
            justify-content: flex-end;
            margin-top: 0.9rem;
        }
        .patient-history-actions a,
        .patient-history-actions button {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.75rem;
            border: 1px solid #e8c8c8;
            border-radius: 7px;
            background: #fff;
            color: #990000;
            font: inherit;
            font-size: 0.8rem;
            text-decoration: none;
            cursor: pointer;
        }
        .patient-history-actions button {
            color: #7f1d1d;
        }
        @media (max-width: 560px) {
            .patient-history-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')
    <div class="module-page">
        <div class="module-page__content">
            <section class="panel patient-history-page">
                <header class="patient-history-header">
                    <div>
                        <h1 class="patient-history-title">{{ $patientName }}</h1>
                        <p class="patient-history-note">
                            {{ $visits->count() }} consumption {{ $visits->count() === 1 ? 'record' : 'records' }}
                        </p>
                    </div>
                    <a class="btn-secondary" href="{{ route('blood-consumption', $filters) }}">
                        <i class="ti ti-arrow-left"></i> Back to patients
                    </a>
                </header>

                @foreach ($visits as $visit)
                    <article class="patient-history-record">
                        <h3>{{ $visit['date'] }}</h3>
                        <dl class="patient-history-details">
                            <div><dt>Age</dt><dd>{{ $visit['age'] }} years</dd></div>
                            <div><dt>Birthdate</dt><dd>{{ $visit['birthdate'] }}</dd></div>
                            <div><dt>Sex</dt><dd>{{ $visit['sex'] }}</dd></div>
                            <div><dt>Blood component</dt><dd>{{ $visit['component'] }}</dd></div>
                            <div><dt>Units</dt><dd>{{ $visit['units'] }}</dd></div>
                            <div><dt>Location</dt><dd>{{ $visit['address'] ?: '—' }}</dd></div>
                        </dl>
                        <div class="patient-history-actions">
                            <a href="{{ route('blood-consumption', ['edit' => $visit['id'], 'patient' => $patientName] + $filters) }}">
                                <i class="ti ti-pencil"></i> Edit
                            </a>
                            <button
                                type="button"
                                data-delete-url="{{ $visit['deleteUrl'] }}"
                                data-delete-name="{{ $patientName }} · {{ $visit['date'] }}"
                            >
                                <i class="ti ti-trash"></i> Delete
                            </button>
                        </div>
                    </article>
                @endforeach
            </section>
        </div>
    </div>
@endsection
