<?php

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

Route::get('/', function () use ($router) {
   return response()->json(['status_code' => 200,'message'=>'Its Alive!'], 200);
});

Route::get('/find/{id}', 'FindController@explore_back');
Route::get('/find-front/{id}', 'FindController@explore_front');

Route::get('/get', 'FindController@getCahe');
Route::get('/clear', 'FindController@clearDB');



