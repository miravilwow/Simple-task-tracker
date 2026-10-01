<?php

use Illuminate\Support\Facades\Route;

// The tracker is the site. `/` redirects rather than 404s, so an old link still lands.
Route::redirect('/', '/tasks');
Route::view('/tasks', 'tasks.index')->name('tasks.index');
