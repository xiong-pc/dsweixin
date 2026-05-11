<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'dsweixin API',
    'docs'    => '/api/v1',
]));
