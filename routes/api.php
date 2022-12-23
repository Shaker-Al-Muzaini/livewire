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
        Route::post('get_one_conversation',[CreateChatController::class, 'getOneConversation']);
        Route::post('get_conversations',[CreateChatController::class, 'getConversations']);

        //sent
        Route::post('send_message', [CreateChatController::class, 'sendMessage']);

        //send an image
        Route::post('/image_messages', [CreateChatController::class, 'submitImage']);

        //send a file
        Route::post('/file_messages', [CreateChatController::class, 'submitFile']);

        //read
        Route::post('read_message', [CreateChatController::class, 'readMessage']);

        //pin
        Route::post('pin_on_message',[CreateChatController::class, 'pinOnMessage']);
        Route::post('pin_off_message',[CreateChatController::class, 'pinOffMessage']);

        //star
        Route::post('star_on_message',[CreateChatController::class, 'starOnMessage']);
        Route::post('star_off_message',[CreateChatController::class, 'starOffMessage']);
        Route::post('star_get_conversation',[CreateChatController::class, 'starGetConversation']);

        //replay
        Route::post('messages_replies', [CreateChatController::class, 'createReplay']);


    });

});
