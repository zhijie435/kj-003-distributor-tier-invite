<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/customer-groups', function () {
    return view('app');
});

Route::get('/customer-groups/{any}', function () {
    return view('app');
})->where('any', '.*');
