<?php

use app\middleware\Admin;
use app\middleware\Auth;
use think\facade\Route;

Route::group('api', function () {
    Route::post('auth/login', 'Auth/login')->completeMatch();
    Route::post('auth/register/send-code', 'Auth/sendRegisterCode')->completeMatch();
    Route::post('auth/register', 'Auth/register')->completeMatch();

    Route::group(function () {
        Route::post('auth/logout', 'Auth/logout')->completeMatch();
        Route::get('auth/user', 'Auth/user')->completeMatch();
        Route::post('play-auth/videos/:id', 'PlayAuth/video')->completeMatch();
        Route::post('play-auth/lessons/:id', 'PlayAuth/lesson')->completeMatch();
    })->middleware(Auth::class);

    Route::group(function () {
        Route::get('grants', 'Grant/index')->completeMatch();
        Route::get('grants/:id', 'Grant/read')->completeMatch();
        Route::post('grants', 'Grant/save')->completeMatch();
        Route::post('grants/:id', 'Grant/update')->completeMatch();
        Route::delete('grants/:id', 'Grant/delete')->completeMatch();
    })->middleware(Admin::class);
});
