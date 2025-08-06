<?php

use App\Livewire\ProgramRegister;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
});

Volt::route('/sign-contract/{programEnrollment}', 'sign-contract')->name('sign-contract');

Route::get('/register', ProgramRegister::class);
