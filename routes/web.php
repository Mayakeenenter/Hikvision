<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HikvisionEventController;

Route::get('/', [HikvisionEventController::class, 'index']);
Route::get('/events', [HikvisionEventController::class, 'index'])->name('events.index');
