<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\CreateChatController;
use App\Http\Controllers\api\MessageController;
use App\Http\Controllers\api\ConversationController;

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
        Route::post('creat_group_conversation',[ConversationController::class, 'creatGroupConversation']);

        //create peer chat
        Route::post('creat_conversation',[ConversationController::class, 'creatConversation']);
        Route::post('get_one_conversation',[ConversationController::class, 'getOneConversation']);
        Route::post('get_conversations',[ConversationController::class, 'getConversations']);

        //create peer chat
        Route::post('creat_encryption_conversation',[ConversationController::class, 'creatEncryptionConversation']);

        //mute
        Route::post('mute_conversation',[ConversationController::class, 'muteConversation']);

        //invite participant to group
        Route::post('invite_participant_conversation',[ConversationController::class, 'inviteParticipantConversation']);

        //change name and image of the group
        Route::post('update_name_conversation',[ConversationController::class, 'updateNameConversation']);
        Route::post('update_image_conversation',[ConversationController::class, 'updateImageConversation']);

        //get the pin message of the conversation
        Route::post('pin_conversation',[ConversationController::class, 'pinConversation']);

        //send message encryption
        Route::post('send_message_encryption', [MessageController::class, 'sendMessageEncryption']);

        //send message
        Route::post('send_message', [MessageController::class, 'sendMessage']);

        //send an image
        Route::post('/image_messages', [MessageController::class, 'submitImage']);

        //send a file
        Route::post('/file_messages', [MessageController::class, 'submitFile']);

        //send a voice
        Route::post('/voice_messages', [MessageController::class, 'submitVoice']);


        //user status
        Route::post('/user_status',  [MessageController::class, 'updateStatus']);

        //read
        Route::post('read_message', [MessageController::class, 'readMessage']);

        //pin
        Route::post('pin_message',[MessageController::class, 'pinMessage']);

        //star
        Route::post('star_message',[MessageController::class, 'starMessage']);
        Route::post('star_get_conversation',[MessageController::class, 'starGetConversation']);
        Route::post('star_get_all',[MessageController::class, 'starGetAll']);

        //replay
        Route::post('messages_replies', [MessageController::class, 'createReplay']);

        //poll
        Route::post('messages_poll', [MessageController::class, 'createPoll']);
        Route::post('poll_vote', [MessageController::class, 'PollVote']);

        //delete message for everyone
        Route::post('delete_for_everyone_message',[MessageController::class, 'deleteForEveryoneMessage']);

        //delete message for me
        Route::post('delete_for_me_message',[MessageController::class, 'deleteForMeMessage']);

        //emoji
        Route::post('emoji_message',[MessageController::class, 'emojiMessage']);

        //forwards
        Route::post('forward_message',[MessageController::class, 'forwardMessage']);

    });

});
