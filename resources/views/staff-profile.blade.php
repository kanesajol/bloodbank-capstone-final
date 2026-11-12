@extends('layouts.app')

@section('title', 'CRH Blood Bank — Staff Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/staff-profile.css') }}">
@endpush

@section('content')
    <div class="module-page">
        <header class="module-page__header">
            <h2>Staff Profile &amp; Account Details</h2>
        </header>
        <div class="module-page__content">
            @if (session('success'))
                <p class="profile-alert profile-alert--success" role="alert">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <p class="profile-alert profile-alert--error" role="alert">{{ $errors->first() }}</p>
            @endif
            <div class="profile-layout">
                <div class="profile-card">
                    <div class="profile-avatar" style="background-color: {{ $staff->avatar_color }}">{{ $staff->avatar_initials }}</div>
                    <h3 class="profile-name">{{ $staff->name }}</h3>
                    <span class="profile-username">{{ '@'.$staff->username }}</span>
                    <span class="profile-role-tag">{{ $staff->role }}</span>
                    <div class="profile-details-list">
                        <div class="detail-item">
                            <span class="detail-label"><i class="ti ti-mail"></i> Work Email:</span>
                            <strong class="detail-val">{{ $staff->email }}</strong>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="ti ti-phone"></i> Contact No:</span>
                            <strong class="detail-val">{{ $staff->contact }}</strong>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="ti ti-clock"></i> Last Login:</span>
                            <strong class="detail-val text-red">{{ $staff->last_login }}</strong>
                        </div>
                    </div>
                </div>

                <div class="profile-right-content">
                    <div class="card-box">
                        <div class="box-header">
                            <h4 class="box-title"><i class="ti ti-user-edit"></i> Edit Profile</h4>
                        </div>
                        <form class="profile-form" method="POST" action="{{ route('staff-profile.update') }}">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="profileName">Full Name</label>
                                    <input id="profileName" name="name" value="{{ old('name', $staff->name) }}" type="text" required maxlength="255">
                                </div>
                                <div class="form-field">
                                    <label for="profileEmail">Work Email</label>
                                    <input id="profileEmail" name="email" value="{{ old('email', $staff->email) }}" type="email" required maxlength="255">
                                </div>
                                <div class="form-field">
                                    <label for="profileContact">Contact No</label>
                                    <input id="profileContact" name="contact" value="{{ old('contact', $staff->contact) }}" type="text" required maxlength="50">
                                </div>
                                <div class="form-field">
                                    <label for="profileRole">Role</label>
                                    <input id="profileRole" name="role" value="{{ old('role', $staff->role) }}" type="text" required maxlength="255">
                                </div>
                            </div>
                            <button class="profile-submit" type="submit">
                                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                                Save Profile
                            </button>
                        </form>
                    </div>

                    <div class="card-box">
                        <div class="box-header">
                            <h4 class="box-title"><i class="ti ti-lock"></i> Change Password</h4>
                        </div>
                        <form class="profile-form" method="POST" action="{{ route('staff-profile.password') }}">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="currentPassword">Current Password</label>
                                    <div class="password-input">
                                        <input id="currentPassword" name="current_password" type="password" required autocomplete="current-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="currentPassword" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="newPassword">New Password</label>
                                    <div class="password-input">
                                        <input id="newPassword" name="password" type="password" required minlength="6" autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="newPassword" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="confirmPassword">Confirm New Password</label>
                                    <div class="password-input">
                                        <input id="confirmPassword" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="confirmPassword" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                </div>
                            </div>
                            <button class="profile-submit" type="submit">
                                <i class="ti ti-lock-check" aria-hidden="true"></i>
                                Change Password
                            </button>
                        </form>
                    </div>

                    <div class="card-box">
                        <div class="box-header">
                            <h4 class="box-title"><i class="ti ti-history"></i> My Automatic Shift &amp; Login Records</h4>
                            <span class="badge">{{ $records->count() }} Recorded</span>
                        </div>
                        <div class="table-wrap">
                            <table class="profile-table">
                                <thead>
                                    <tr>
                                        <th>Logged In</th>
                                        <th>Logged Out</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($records as $rec)
                                        <tr>
                                            <td><strong class="text-red">{{ $rec->loginLabel() }}</strong></td>
                                            <td>{{ $rec->isOpen() ? 'Signed in' : $rec->logoutLabel() }}</td>
                                            <td>{{ \App\Support\BloodBank::formatDate($rec->login_date, 'M j, Y') }}</td>
                                            <td>
                                                <span class="duty-badge {{ $rec->status === 'On Duty' ? 'active' : 'completed' }}">{{ $rec->status }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="empty-state">No shift logs found for this account.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
