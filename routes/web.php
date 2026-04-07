<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;

Route::get('/',[TaskController::class, 'index']);
Route::get('/tasks/create',[TaskController::class, 'create'])->middleware('auth');
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->middleware('auth');
Route::get('/tasks/edit/{id}', [TaskController::class, 'edit'])->middleware('auth');
Route::put('/tasks/update/{id}', [TaskController::class, 'update'])->middleware('auth');

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/dashboard', [TaskController::class, 'dashboard'])->middleware('auth');

Route::get('/tarefas', function () {
    return view('tasks');
});


