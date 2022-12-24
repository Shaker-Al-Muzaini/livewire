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

        //create group chat
        Route::post('creat_group_conversation',[CreateChatController::class, 'creatGroupConversation']);

        //create peer chat
        Route::post('creat_conversation',[CreateChatController::class, 'creatConversation']);
        Route::post('get_one_conversation',[CreateChatController::class, 'getOneConversation']);
        Route::post('get_conversations',[CreateChatController::class, 'getConversations']);

        //sent
        Route::post('send_message', [CreateChatController::class, 'sendMessage']);

        //send an image
        Route::post('/image_messages', [CreateChatController::class, 'submitImage']);

        //send a file
        Route::post('/file_messages', [CreateChatController::class, 'submitFile']);

        //user status
        Route::post('/user_status',  [CreateChatController::class, 'updateStatus']);

        //read
        Route::post('read_message', [CreateChatController::class, 'readMessage']);

        //pin
        Route::post('pin_message',[CreateChatController::class, 'pinMessage']);

        //star
        Route::post('star_message',[CreateChatController::class, 'starMessage']);
        Route::post('star_get_conversation',[CreateChatController::class, 'starGetConversation']);
//        Route::post('star_get_all',[CreateChatController::class, 'starGetAll']);

        //replay
        Route::post('messages_replies', [CreateChatController::class, 'createReplay']);


    });

});
