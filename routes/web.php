<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BloodCollectionController;
use App\Http\Controllers\BloodConsumptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffAccountController;
use App\Http\Middleware\RecordStaffPresence;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', RecordStaffPresence::class])->prefix('dashboard')->group(function () {
    Route::redirect('/', '/dashboard/blood-collection')->name('dashboard');
    Route::post('/donors', [DashboardController::class, 'storeDonor'])->name('dashboard.donors.store');
    Route::delete('/donors/{donor}', [DashboardController::class, 'destroyDonor'])->name('donors.destroy');

    Route::get('/profile', [StaffAccountController::class, 'profile'])->name('staff-profile');
    Route::patch('/profile', [StaffAccountController::class, 'updateProfile'])->name('staff-profile.update');
    Route::patch('/profile/password', [StaffAccountController::class, 'changePassword'])->name('staff-profile.password');

    Route::get('/blood-collection', [BloodCollectionController::class, 'index'])->name('blood-collection');
    Route::delete('/blood-collection/donors', [BloodCollectionController::class, 'destroyAllDonors'])->name('donors.destroy-all');
    Route::post('/blood-collection/donors', [DonorController::class, 'store'])->name('donors.store');
    Route::post('/blood-collection/donors/{donor}/donations', [BloodCollectionController::class, 'storeDonation'])->name('donations.store');
    Route::delete('/blood-collection/donations/{donation}', [BloodCollectionController::class, 'destroyDonation'])->name('donations.destroy');

    Route::get('/blood-consumption', [BloodConsumptionController::class, 'index'])->name('blood-consumption');
    Route::post('/blood-consumption', [BloodConsumptionController::class, 'store'])->name('consumption.store');
    Route::patch('/blood-consumption/{consumption}', [BloodConsumptionController::class, 'update'])->name('consumption.update');
    Route::delete('/blood-consumption', [BloodConsumptionController::class, 'destroyAll'])->name('consumption.destroy-all');
    Route::delete('/blood-consumption/{consumption}', [BloodConsumptionController::class, 'destroy'])->name('consumption.destroy');

    Route::get('/reports/consumption-details', [ReportController::class, 'consumptionDetails'])->name('reports.consumption-details');
    Route::get('/reports/demographic-details', [ReportController::class, 'demographicDetails'])->name('reports.demographic-details');
    Route::get('/reports/consumption-demographic-details', [ReportController::class, 'consumptionDemographicDetails'])->name('reports.consumption-demographic-details');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    Route::post('/presence', fn () => response()->noContent())->name('staff.presence');
    Route::get('/staff-accounts', [StaffAccountController::class, 'index'])->name('staff-accounts');
    Route::get('/staff-log', [StaffAccountController::class, 'log'])->name('staff-log');
    Route::post('/staff-accounts', [StaffAccountController::class, 'store'])->name('staff.store');
    Route::delete('/staff-accounts/{user}', [StaffAccountController::class, 'destroy'])->name('staff.destroy');
});
