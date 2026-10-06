<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DemoSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentOfferController;
use App\Http\Controllers\TalentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/tentang', 'about')->name('about');
Route::view('/untuk-perusahaan', 'companies')->name('companies');
Route::get('/kontak', [ContactController::class, 'show'])->name('contact');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

Route::get('/talenta', [TalentController::class, 'index'])->name('talents.index');
Route::get('/talenta/{talent}', [TalentController::class, 'show'])->name('talents.show');
Route::post('/talenta/{talent}/tawaran', [RecruitmentOfferController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('offers.store');

Route::get('/lowongan', [JobPostingController::class, 'index'])->name('jobs.index');

Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
Route::post('/profil', [ProfileController::class, 'store'])->name('profile.store');

Route::post('/demo/masuk', [DemoSessionController::class, 'store'])->name('demo.login');
Route::post('/demo/keluar', [DemoSessionController::class, 'destroy'])->name('demo.logout');
