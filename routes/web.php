<?php

use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::view('/', 'pages.home')->name('home');
Route::view('/data-remunerasi', 'pages.data-remunerasi')->name('data');
