<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;

Route::get('/',[TaskController::class, 'index']);
Route::get('/tasks/create',[TaskController::class, 'create'])->middleware(['auth', 'verified']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::post('/tasks', [TaskController::class, 'store'])->middleware(['auth', 'verified']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->middleware(['auth', 'verified']);
Route::get('/tasks/edit/{id}', [TaskController::class, 'edit'])->middleware(['auth', 'verified']);
Route::put('/tasks/update/{id}', [TaskController::class, 'update'])->middleware(['auth', 'verified']);

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/dashboard', [TaskController::class, 'dashboard'])->middleware(['auth', 'verified']);

Route::post('/tasks/join/{id}', [TaskController::class, 'joinTask'])->middleware(['auth', 'verified']);




