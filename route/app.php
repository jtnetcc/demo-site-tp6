<?php

use app\middleware\WebAdmin;
use app\middleware\WebAuth;
use think\facade\Route;

Route::get('ping', function () {
    return json(['data' => ['pong' => true], 'message' => '操作成功']);
});

Route::get('/', 'Index/index');

Route::rule('login', 'WebAuth/login', 'GET|POST');
Route::rule('register', 'WebAuth/register', 'GET|POST');
Route::get('logout', function () {
    session('flash_error', '请从页面按钮退出登录');
    return redirect('/login');
});
Route::post('logout', 'WebAuth/logout')->middleware(WebAuth::class);

Route::get('playback/video', 'Playback/video');

Route::get('videos', 'Video/index')->completeMatch();
Route::get('videos/:id', 'Video/read');
Route::post('videos/:id/play-auth', 'Video/playAuth')->middleware(WebAuth::class);
Route::post('videos/:id/like', 'Video/like')->middleware(WebAuth::class);
Route::post('videos/:id/favorite', 'Video/favorite')->middleware(WebAuth::class);
Route::post('videos/:id/comment', 'Video/comment')->middleware(WebAuth::class);

Route::get('courses', 'Course/index')->completeMatch();
Route::get('course/:courseId/lesson/:lessonId', 'Lesson/read')->middleware(WebAuth::class)->completeMatch();
Route::post('course/:courseId/lesson/:lessonId/play-auth', 'Lesson/playAuth')->middleware(WebAuth::class)->completeMatch();
Route::get('course/:id', 'Course/read')->completeMatch();

Route::get('search', 'Search/index');

Route::group(function () {
    Route::get('me', 'Me/index');
    Route::rule('me/profile', 'Me/profile', 'GET|POST');
    Route::get('me/history', 'Me/history');
    Route::get('me/favorites', 'Me/favorites');
    Route::get('my-courses', 'Me/courses');
})->middleware(WebAuth::class);

Route::group('admin', function () {
    Route::get('', 'Admin/index');

    Route::get('users', 'AdminUser/index');
    Route::get('users/create', 'AdminUser/create');
    Route::post('users', 'AdminUser/save')->completeMatch();
    Route::get('users/:id/edit', 'AdminUser/edit');
    Route::post('users/:id/delete', 'AdminUser/delete');
    Route::post('users/:id', 'AdminUser/update')->completeMatch();

    Route::get('videos', 'AdminVideo/index');
    Route::get('videos/create', 'AdminVideo/create');
    Route::post('videos', 'AdminVideo/save')->completeMatch();
    Route::get('videos/:id/edit', 'AdminVideo/edit');
    Route::post('videos/:id/delete', 'AdminVideo/delete');
    Route::post('videos/:id', 'AdminVideo/update')->completeMatch();

    Route::get('categories', 'AdminCategory/index');
    Route::get('categories/create', 'AdminCategory/create');
    Route::post('categories', 'AdminCategory/save')->completeMatch();
    Route::get('categories/:id/edit', 'AdminCategory/edit');
    Route::post('categories/:id/delete', 'AdminCategory/delete');
    Route::post('categories/:id', 'AdminCategory/update')->completeMatch();

    Route::get('tags', 'AdminTag/index');
    Route::get('tags/create', 'AdminTag/create');
    Route::post('tags', 'AdminTag/save')->completeMatch();
    Route::get('tags/:id/edit', 'AdminTag/edit');
    Route::post('tags/:id/delete', 'AdminTag/delete');
    Route::post('tags/:id', 'AdminTag/update')->completeMatch();

    Route::get('courses', 'AdminCourse/index');
    Route::get('courses/create', 'AdminCourse/create');
    Route::post('courses', 'AdminCourse/save')->completeMatch();
    Route::get('courses/:id/edit', 'AdminCourse/edit');
    Route::post('courses/:id/delete', 'AdminCourse/delete');
    Route::post('courses/:id', 'AdminCourse/update')->completeMatch();

    Route::get('lessons', 'AdminLesson/index');
    Route::get('lessons/create', 'AdminLesson/create');
    Route::post('lessons', 'AdminLesson/save')->completeMatch();
    Route::get('lessons/:id/edit', 'AdminLesson/edit');
    Route::post('lessons/:id/delete', 'AdminLesson/delete');
    Route::post('lessons/:id', 'AdminLesson/update')->completeMatch();

    Route::get('grants', 'AdminGrant/index');
    Route::get('grants/create', 'AdminGrant/create');
    Route::post('grants', 'AdminGrant/save')->completeMatch();
    Route::get('grants/:id/edit', 'AdminGrant/edit');
    Route::post('grants/:id/delete', 'AdminGrant/delete');
    Route::post('grants/:id', 'AdminGrant/update')->completeMatch();

    Route::get('watch-history', 'AdminWatchHistory/index');
    Route::get('watch-history/create', 'AdminWatchHistory/create');
    Route::post('watch-history', 'AdminWatchHistory/save')->completeMatch();
    Route::get('watch-history/:id/edit', 'AdminWatchHistory/edit');
    Route::post('watch-history/:id/delete', 'AdminWatchHistory/delete');
    Route::post('watch-history/:id', 'AdminWatchHistory/update')->completeMatch();

    Route::get('comments', 'AdminComment/index');
    Route::post('comments/:id/hide', 'AdminComment/hide');
    Route::post('comments/:id/show', 'AdminComment/show');
    Route::post('comments/:id/delete', 'AdminComment/delete');

    Route::get('import-tasks', 'AdminImportTask/index');
    Route::get('import-tasks/create', 'AdminImportTask/create');
    Route::post('import-tasks', 'AdminImportTask/save')->completeMatch();
    Route::get('import-tasks/:id', 'AdminImportTask/read');
    Route::post('import-tasks/:id/process', 'AdminImportTask/process');
    Route::post('import-tasks/:id/delete', 'AdminImportTask/delete');
    Route::post('import-tasks/:id', 'AdminImportTask/update')->completeMatch();

    Route::get('settings', 'AdminSetting/index');
    Route::post('settings', 'AdminSetting/update')->completeMatch();
})->middleware(WebAdmin::class);
