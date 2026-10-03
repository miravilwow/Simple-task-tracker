<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/tasks/stats', [TaskController::class, 'stats']);
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
// Before /tasks/{task} is irrelevant for POST, but it is grouped with the other writes so the
// table's selection toolbar is findable beside the single-task actions it stands in for.
Route::post('/tasks/bulk', [TaskController::class, 'bulk']);
// withTrashed, so View details works on the Deleted list. Only reading reaches a deleted task:
// every write below keeps the default binding and still answers 404 for one.
Route::get('/tasks/{task}', [TaskController::class, 'show'])->withTrashed();
Route::patch('/tasks/{task}', [TaskController::class, 'update']);
Route::patch('/tasks/{task}/start', [TaskController::class, 'start']);
Route::patch('/tasks/{task}/review', [TaskController::class, 'review']);
Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen']);
Route::patch('/tasks/{task}/reorder', [TaskController::class, 'reorder']);
Route::patch('/tasks/{task}/schedule', [TaskController::class, 'schedule']);
Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
// withTrashed, because the whole point of restore is to reach a task the default binding hides.
Route::patch('/tasks/{task}/restore', [TaskController::class, 'restore'])->withTrashed();

// scopeBindings, or a sub-task could be reached through a task it does not belong to.
Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store']);
Route::patch('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update'])->scopeBindings();
Route::delete('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->scopeBindings();

Route::get('/categories', [CategoryController::class, 'index']);
