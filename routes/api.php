<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisitController;

Route::post('/track', [VisitController::class, 'store']);
Route::get('/track', [VisitController::class, 'index']);
