<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pasien', [PatientController::class, 'index'])->name('patients.index');
    Route::post('/pasien', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/pasien/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::put('/pasien/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::delete('/pasien/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
    Route::post('/pasien/{patient}/upload', [UploadController::class, 'store'])->name('patients.upload');

    Route::get('/rekaman', [RecordingController::class, 'index'])->name('recordings.index');
    Route::get('/rekaman/{recording}', [RecordingController::class, 'show'])->name('recordings.show');

    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/alat-ekg', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/alat-ekg', [DeviceController::class, 'store'])->name('devices.store');
    Route::put('/alat-ekg/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('/alat-ekg/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::get('/api/monitoring/latest', [MonitoringController::class, 'latest'])->name('monitoring.latest');
    Route::get('/api/patients/{patient}/bpm-trend', [PatientController::class, 'bpmTrend'])->name('patients.bpm-trend');
    Route::get('/api/recordings/{recording}/chart-data', [RecordingController::class, 'chartData'])->name('recordings.chart-data');
});
