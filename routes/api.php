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
    Route::post('createMessage', 'IDigimaController@createMessage');
    Route::post('isTokenValid', 'IDigimaController@isTokenValid');
});

/* Office365 API */
Route::group(array('prefix' => 'office365'), function()
{
    /* GET */
    Route::get('notifications', 'Office365Controller@notifications');
    Route::get('authenticate', 'Office365Controller@authenticate');

    /* POST */
    Route::post('isTokenValid', 'Office365Controller@isTokenValid');
});

/* Users API */
Route::post('users', 'UsersController@store');