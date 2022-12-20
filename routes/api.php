<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\CreateChatController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {



    Route::prefix('user')->group(function () {

        Route::post('creat_conversation',[CreateChatController::class, 'creatConversation']);
        Route::post('get_conversations',[CreateChatController::class, 'getConversations']);

        Route::post('/send_message', [CreateChatController::class, 'sendMessage']);
        Route::post('/read_message', [CreateChatController::class, 'readMessage']);

    });

});
