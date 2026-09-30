<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/tasks/stats', [TaskController::class, 'stats']);
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen']);
Route::patch('/tasks/{task}/schedule', [TaskController::class, 'schedule']);
Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
// withTrashed, because the whole point of restore is to reach a task the default binding hides.
Route::patch('/tasks/{task}/restore', [TaskController::class, 'restore'])->withTrashed();

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::patch('/categories/{category}', [CategoryController::class, 'update']);
Route::get('/categories/{category}/activity', [CategoryController::class, 'activity']);
Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
