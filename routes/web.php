<?php

use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DemoSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobPostingFlowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentOfferController;
use App\Http\Controllers\TalentController;
use App\Http\Controllers\TalentInboxController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', HomeController::class)->name('home');
Route::view('/tentang', 'about')->name('about');
Route::view('/untuk-perusahaan', 'companies')->name('companies');
Route::get('/kontak', [ContactController::class, 'show'])->name('contact');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/talenta', [TalentController::class, 'index'])->name('talents.index');
Route::get('/talenta/{talent}', [TalentController::class, 'show'])->name('talents.show');
Route::get('/lowongan', [JobPostingController::class, 'index'])->name('jobs.index');
Route::get('/lowongan/{job}', [JobPostingController::class, 'show'])->whereNumber('job')->name('jobs.show');
Route::get('/mitra/{company}', [JobPostingController::class, 'company'])->name('companies.show');
Route::view('/kebijakan-privasi', 'legal.privacy')->name('privacy');
Route::view('/syarat-penggunaan', 'legal.terms')->name('terms');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.store');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register');
    Route::post('/demo/masuk', [DemoSessionController::class, 'store'])->name('demo.login');
    Route::get('/lupa-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-sandi', [PasswordResetController::class, 'update'])->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verifikasi', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verifikasi/{id}/{hash}', [VerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verifikasi/kirim-ulang', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/talenta/{talent}/dokumen/{type}', [TalentController::class, 'document'])->name('talents.document');
    Route::get('/admin-file/{type}/{id}', AdminFileController::class)->name('admin.file');
});

// Talenta
Route::middleware(['auth', 'verified', 'role:talenta'])->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profil', [ProfileController::class, 'store'])->name('profile.store');
    Route::post('/profil/status', [ProfileController::class, 'updateStatus'])->name('profile.status');
    Route::get('/tawaran', [TalentInboxController::class, 'offers'])->name('talent.offers');
    Route::post('/tawaran/{offer}/jawab', [TalentInboxController::class, 'respond'])->name('talent.offers.respond');
    Route::get('/lamaran', [TalentInboxController::class, 'applications'])->name('talent.applications');
    Route::post('/lowongan/{job}/lamar', [TalentInboxController::class, 'apply'])->middleware('throttle:20,1')->name('jobs.apply');
});

// Perusahaan
Route::middleware(['auth', 'verified', 'role:perusahaan'])->group(function () {
    Route::get('/perusahaan/profil', [CompanyProfileController::class, 'edit'])->name('company.profile');
    Route::post('/perusahaan/profil', [CompanyProfileController::class, 'update'])->name('company.profile.update');
    Route::post('/talenta/{talent}/tawaran', [RecruitmentOfferController::class, 'store'])->middleware('throttle:10,1')->name('offers.store');
    Route::get('/lowongan/pasang', [JobPostingFlowController::class, 'create'])->name('jobs.posting.create');
    Route::post('/lowongan/pasang', [JobPostingFlowController::class, 'store'])->middleware('throttle:10,1')->name('jobs.posting.store');
    Route::get('/lowongan/pasang/{job}', [JobPostingFlowController::class, 'status'])->name('jobs.posting.status');
    Route::get('/perusahaan/tawaran', [CompanyDashboardController::class, 'offers'])->name('company.offers');
    Route::get('/perusahaan/lowongan', [CompanyDashboardController::class, 'jobs'])->name('company.jobs');
    Route::get('/perusahaan/lowongan/{job}/pelamar', [CompanyDashboardController::class, 'applicants'])->name('company.applicants');
    Route::post('/perusahaan/lamaran/{application}/status', [CompanyDashboardController::class, 'updateApplication'])->name('company.applications.update');
});
