<?php

use app\middleware\Admin;
use app\middleware\Auth;
use think\facade\Route;

Route::group('api', function () {
    Route::post('auth/login', 'Auth/login');
    Route::post('auth/register', 'Auth/register');

    Route::group(function () {
        Route::post('auth/logout', 'Auth/logout');
        Route::get('auth/user', 'Auth/user');
        Route::post('play-auth/videos/:id', 'PlayAuth/video');
        Route::post('play-auth/lessons/:id', 'PlayAuth/lesson');
    })->middleware(Auth::class);

    Route::group(function () {
        Route::get('grants', 'Grant/index');
        Route::get('grants/:id', 'Grant/read');
        Route::post('grants', 'Grant/save');
        Route::post('grants/:id', 'Grant/update');
        Route::delete('grants/:id', 'Grant/delete');
    })->middleware(Admin::class);
});
