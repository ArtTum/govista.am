<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('/admin/{any?}', 'admin')->where('any', '.*');
