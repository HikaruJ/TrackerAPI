<?php

use Illuminate\Http\Request;

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

/* IDigima API */
Route::group(array('prefix' => 'idigima'), function()
{
    /* GET */
    Route::get('saveToken', 'IDigimaController@saveToken');

    /* POST */
    Route::post('isTokenValid', 'IDigimaController@isTokenValid');
});

/* Office365 API */
Route::group(array('prefix' => 'office365'), function()
{
    /* GET */
    Route::get('authenticate', 'Office365Controller@authenticate');

    /* POST */
    Route::post('isTokenValid', 'Office365Controller@isTokenValid');
});

/* Users API */
Route::group(array('prefix' => 'users'), function()
{
    /* POST */
    Route::post('checkActivationFlow', 'UsersController@checkActivationFlow');
    Route::post('saveUser', 'UsersController@store');
});