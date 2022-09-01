<?php

use App\Http\Controllers\Api\TasksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::patch('/todos/{todo}/finish', [TasksController::class, 'finish']);

Route::apiResource('/todos', TasksController::class, ['parameters' => [
    'todo' => 'id'
]]);

Route::pattern('id', '[0-9]+');
