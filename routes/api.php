<?php

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/tasks/stats', [TaskController::class, 'stats']);
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen']);
Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
