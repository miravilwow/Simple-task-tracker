<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/tasks', 'tasks.index')->name('tasks.index');
