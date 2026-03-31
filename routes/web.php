<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;

Route::get('/',[TaskController::class, 'index']);
Route::get('/tasks/create',[TaskController::class, 'create']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::post('/tasks', [TaskController::class, 'store']);

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/tarefas', function () {
    return view('tasks');
});

