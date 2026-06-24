<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/pasien', [PatientController::class, 'index'])->name('patients.index');
Route::post('/pasien', [PatientController::class, 'store'])->name('patients.store');
Route::put('/pasien/{patient}', [PatientController::class, 'update'])->name('patients.update');
Route::delete('/pasien/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
Route::post('/pasien/{patient}/upload', [UploadController::class, 'store'])->name('patients.upload');

Route::get('/rekaman', [RecordingController::class, 'index'])->name('recordings.index');
Route::get('/rekaman/{recording}', [RecordingController::class, 'show'])->name('recordings.show');

Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
Route::get('/api/monitoring/latest', [MonitoringController::class, 'latest'])->name('monitoring.latest');
