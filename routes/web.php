<?php

use App\Http\Controllers\DirectUploadOssController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 阿里 OSS 直传配置
Route::get('direct-upload-oss-config', [DirectUploadOssController::class, 'config']);

// 阿里 OSS 直传回调
Route::post('direct-upload-oss-callback', [DirectUploadOssController::class, 'callback'])->name('api.direct.upload.oss.callback');


