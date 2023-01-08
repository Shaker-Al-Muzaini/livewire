<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\DeletedMessage;
use App\Models\EmojiMessage;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\StarredMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pusher\Pusher;

class MessageController extends Controller
{
    public function sendMessage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $request->message,
                'conversations_id' => $request->conversations_id,
                'parent_id' => $request->parent_id
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);
            DB::commit();

            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc')->with([
                    'pollVotes' => function($query){
                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['PollVoteUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }]);
                    }
                ]);
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['emojimessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['deletemessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'message-sent', [
                'user_id' => $request->user_id,
                'message_id' => $message->id,
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $newMessage,

            ]);


        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function sendMessageEncryption(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );



            $user_sender = User::find($request->user_id);

            $iv ="NrhWvCbY77YobjIdrorgkQ==";
            $value = $request->message;
            $mac = exec('getmac');

            $mac_address = $request->mac_address;
            $mac_type = $request->mac_type;

            $payload =[
                'iv' => $iv,
                'mac' => $mac,
                'message' =>$value,
            ];

            $coded = base64_encode(json_encode($payload));

            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $value,
                'conversations_id' => $request->conversations_id,
                'parent_id' => $request->parent_id
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            DB::commit();

            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $newMessage->message =base64_encode(json_encode($payload));

            $pusher->trigger('livewire-chat', 'message-sent', [
                'user_id' => $request->user_id,
                'message_id' => $message->id,
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
            ]);

            return response()->json([
                'status' => 'success',
            ]);


        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function readMessage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

//            $conversation = Conversation::with('messages')->where('id', $request->conversation_id)->get();
            $conversation = Conversation::find($request->conversation_id);
            $messages = $conversation->messages()->where('user_id','!=', $request->user_id)->update([
                'read' => true
            ]);

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $pusher->trigger('livewire-chat', 'message-read',[
                'messages' => $messages,
                'conversation_id' => $request->conversation_id,
                'user_id' => $request->user_id,
            ]);


            DB::commit();
            return response()->json([
                'status' => 'success',
            ]);


        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function submitImage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {
            // Validate the request data
            $this->validate($request, [
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:16384',
            ]);

            $imageName = Str::random(32) . "." . $request->image->getClientOriginalExtension();


            // If an image was uploaded, store it in the file system or cloud storage
            if ($request->hasFile('image')) {
                Storage::disk('public')->put('images/' . $imageName, file_get_contents($request->image));
            }

            $path = 'http://vela-test-chat.pal-lady.com/storage/app/public/images/' . $imageName;


            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $path,
                'conversations_id' => $request->conversations_id,
                'is_image' => true,
                'parent_id' => $request->parent_id
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            // Trigger a new-image-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            DB::commit();
            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc')->with([
                    'pollVotes' => function($query){
                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['PollVoteUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }]);
                    }
                ]);
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['emojimessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['deletemessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-image-message', [
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
                'user_id' => $request->user_id
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }

    }

    public function submitFile(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {
            // Validate the request data
            $this->validate($request, [
                'file' => 'file|max:16384',
            ]);

            $imageName = Str::random(32) . "." . $request->file->getClientOriginalExtension();

            // If a file was uploaded, store it in the file system or cloud storage
            if ($request->hasFile('file')) {
                Storage::disk('public')->put('files/' . $imageName, file_get_contents($request->file));
            }

            $path = 'http://vela-test-chat.pal-lady.com/storage/app/public/files/' . $imageName;


            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $path,
                'conversations_id' => $request->conversations_id,
                'is_file' => true,
                'parent_id' => $request->parent_id
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            // Trigger a new-file-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );
            DB::commit();


            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc')->with([
                    'pollVotes' => function($query){
                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['PollVoteUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }]);
                    }
                ]);
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['emojimessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['deletemessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-file-message', [
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
                'user_id' => $request->user_id
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }

    }

    public function submitVoice(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {
            // Validate the request data
            $this->validate($request, [
                'file' => 'file|max:16384',
            ]);

            $imageName = Str::random(32) . "." . $request->file->getClientOriginalExtension();

            // If a voice was uploaded, store it in the file system or cloud storage
            if ($request->hasFile('file')) {
                Storage::disk('public')->put('voices/' . $imageName, file_get_contents($request->file));
            }

            $path = 'http://vela-test-chat.pal-lady.com/storage/app/public/voices/' . $imageName;


            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $path,
                'conversations_id' => $request->conversations_id,
                'is_voice' => true,
                'parent_id' => $request->parent_id
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            // Trigger a new-voice-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );
            DB::commit();

            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc')->with([
                    'pollVotes' => function($query){
                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['PollVoteUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }]);
                    }
                ]);
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['emojimessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['deletemessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-voice-message', [
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
                'user_id' => $request->user_id
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }

    }

    public function updateStatus(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $this->validate($request, [
                'user_id' => 'required',
                'status' => 'required|in:online,offline',
            ]);

            $user = User::findOrFail($request->user_id);
            $user->status = $request->status;
            $user->save();

            // Trigger a user-status-changed event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $pusher->trigger('livewire-chat', 'user-status-changed', [
                'user_id' => $user->id,
                'status' => $user->status,
            ]);

            DB::commit();
            return response()->json([
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function pinMessage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {


            $pinned = PinnedMessage::where('user_id',$request->user_id)
                ->where('conversations_id', $request->conversations_id)->first();

            if($pinned != null){

                if($pinned->message_id != $request->message_id){

                    $pinned->delete();

                    PinnedMessage::create([
                        'user_id' => $request->user_id,
                        'conversations_id' => $request->conversations_id,
                        'message_id' => $request->message_id,
                        'pin' => true,
                    ]);

                }else{
                    $message_pin = PinnedMessage::where('message_id',$request->message_id)
                        ->where('user_id', $request->user_id)->where('conversations_id', $request->conversations_id)->delete();

                }
            }else{
                $message_pin = PinnedMessage::where('message_id',$request->message_id)
                    ->where('user_id', $request->user_id)->where('conversations_id', $request->conversations_id)->first();

                if($message_pin != null){

                    $message_pin->delete();

                }else{

                    PinnedMessage::create([
                        'user_id' => $request->user_id,
                        'conversations_id' => $request->conversations_id,
                        'message_id' => $request->message_id,
                        'pin' => true,
                    ]);

                }
            }


            DB::commit();
            $message = Message::with('pinmessages')->find($request->message_id);

            $message_pin = PinnedMessage::with([
                'MessagePinnedMessage' => function ($query){
                    $query->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        },

                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'pin')->
            where('message_id',$request->message_id)->latest()->get();


            // Trigger a message-pinned event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $pusher->trigger('livewire-chat', 'message-pinned', [
                'message' => $message->id,
                'message_pin' => $message_pin[0] ?? []
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'message_pin' => $message_pin[0] ?? []
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function starMessage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {


            $message_star = StarredMessage::where('message_id',$request->message_id)
                ->where('user_id', $request->user_id)->first();


            if($message_star != null){

                $message_star->delete();

            }else{

                StarredMessage::create([
                    'user_id' => $request->user_id,
                    'message_id' => $request->message_id,
                    'star' => true,
                ]);

            }

            // Trigger a new-star-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );


            DB::commit();
            $message = Message::with('starmessages')->find($request->message_id);

            $message_star = StarredMessage::with([
                'MessageStarredMessage' => function ($query){
                    $query->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        },

                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'star')->
            where('message_id',$request->message_id)->latest()->get();

            $pusher->trigger('livewire-chat', 'message-starred', [
                'message' => $message->id,
                'message_star' => $message_star[0] ?? []
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'message_star' => $message_star[0] ?? []
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function starGetConversation(Request $request): \Illuminate\Http\JsonResponse
    {

        try {

            $message_star = StarredMessage::with([
                'MessageStarredMessage' => function ($query){
                    $query->select('id','user_id', 'conversations_id')->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }
                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'star')->find($request->star_message);

            return response()->json([
                'status' => 'success',
                'message_star' => $message_star,
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function starGetAll(Request $request): \Illuminate\Http\JsonResponse
    {

        try {

            $message_star = StarredMessage::with([
                'MessageStarredMessage' => function ($query){
                    $query->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        },

                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'star')->
            where('user_id',$request->user_id)->latest()->get();

            return response()->json([
                'status' => 'success',
                'message_star' => $message_star,
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()
            ], 500);
        }
    }

//    public function createReplay(Request $request): \Illuminate\Http\JsonResponse
//    {
//
//        DB::beginTransaction();
//        try {
//
//            $pusher = new Pusher(
//                '6b9ab9b8a817a7857923',
//                '2189a62314214f15c216',
//                '1526965',
//                array('cluster' => 'mt1')
//            );
//
//            $message = Message::create([
//                'user_id' => $request->user_id,
//                'message' => $request->message,
//                'conversations_id' => $request->conversations_id,
//                'parent_id' => $request->parent_id
//            ]);
//
//            $conversation = Conversation::where('id', $request->conversations_id)->update([
//                'last_time_message' => $message->created_at
//            ]);
//
//            $newMessage = Message::with(['parent' => function($query) {
//                $query->orderBy('created_at', 'desc');
//            }])->with(['polls' => function($query) {
//                $query->orderBy('created_at', 'desc')->with([
//                    'pollVotes' => function($query){
//                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
//                            $query->orderBy('created_at', 'desc');
//                        }])->with(['PollVoteUser' => function ($query) {
//                            $query->select('id', 'full_name', 'image');
//                        }]);
//                    }
//                ]);
//            }])->with(['MessageUser' => function ($query) {
//                $query->select('id', 'full_name', 'image');
//            }])->with(['starmessages' => function($query) {
//                $query->orderBy('created_at', 'desc');
//            }])->with(['pinmessages' => function($query) {
//                $query->orderBy('created_at', 'desc');
//            }])->with(['emojimessages' => function($query) {
//                $query->orderBy('created_at', 'desc');
//            }])->find($message->id);
//
//            $pusher->trigger('livewire-chat', 'message-replay', [
////                'parent_id' => $request->parent_id,
//                'user_id' => $request->user_id,
////                'message_id' => $message->id,
//                'message' => $newMessage,
//                'message_body' => $request->message,
//                'conversations_id' => $request->conversations_id,
//            ]);
//
//            DB::commit();
//            return response()->json([
//                'status' => 'success',
//            ]);
//
//        } catch (\Exception $e) {
//            // Return Json Response
//            DB::rollBack();
//            return response()->json([
//                'message' => "Something went really wrong!",
//                'error' => $e->getMessage()
//
//            ], 500);
//        }
//    }

    public function createPoll(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $object = json_decode($request->poll_options);

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $request->message,
                'conversations_id' => $request->conversations_id,
                'is_poll' => true,
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            foreach($object as $key => $data)
            {
                $poll_options = Poll::create([
                    'message_id' => $message->id,
                    'poll_options' => $data
                ]);
            }

            DB::commit();

            $newMessage = Message::with(['parent' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['polls' => function($query) {
                $query->orderBy('created_at', 'desc')->with([
                    'pollVotes' => function($query){
                        $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['PollVoteUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }]);
                    }
                ]);
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['emojimessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['deletemessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);


            $pusher->trigger('livewire-chat', 'message-poll', [
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
                'user_id' => $request->user_id
            ]);

            return response()->json([
                'status' => 'success',
                'conversations_id' => $request->conversations_id,
                'user_id' => $request->user_id
            ], 200);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function PollVote(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );


            $polls = PollVote::where('user_id', $request->user_id)->where('poll_id', $request->poll_id)->first();
            if($polls == null){

                $poll = Poll::find($request->poll_id);
                $poll->rate = $poll->rate + $request->rate;
                $poll->save();

                $poll_vote = PollVote::create([
                    'user_id' => $request->user_id,
                    'poll_id' => $request->poll_id
                ]);

                DB::commit();

                $newMessage = Message::with(['parent' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['polls' => function($query) {
                    $query->orderBy('created_at', 'desc')->with([
                        'pollVotes' => function($query){
                            $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                                $query->orderBy('created_at', 'desc');
                            }])->with(['PollVoteUser' => function ($query) {
                                $query->select('id', 'full_name', 'image');
                            }]);
                        }
                    ]);
                }])->with(['MessageUser' => function ($query) {
                    $query->select('id', 'full_name', 'image');
                }])->with(['starmessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['pinmessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['emojimessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['deletemessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->find($poll->message_id);

                $pusher->trigger('livewire-chat', 'poll-vote', [
                    'poll_vote' => $newMessage,
                ]);

                return response()->json([
                    'status' => 'success',
                ], 200);

            }else{

                $poll = Poll::find($request->poll_id);
                $poll->rate = $poll->rate - 1;
                $poll->save();

                $polls->delete();

                DB::commit();

                $newMessage = Message::with(['parent' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['polls' => function($query) {
                    $query->orderBy('created_at', 'desc')->with([
                        'pollVotes' => function($query){
                            $query->orderBy('created_at', 'desc')->with(['PollVotePoll' => function($query) {
                                $query->orderBy('created_at', 'desc');
                            }])->with(['PollVoteUser' => function ($query) {
                                $query->select('id', 'full_name', 'image');
                            }]);
                        }
                    ]);
                }])->with(['MessageUser' => function ($query) {
                    $query->select('id', 'full_name', 'image');
                }])->with(['starmessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['pinmessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['emojimessages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->find($poll->message_id);

                $pusher->trigger('livewire-chat', 'poll-vote', [
                    'poll_vote' => $newMessage,
                ]);

                return response()->json([
                    'status' => 'success',
                ], 200);


            }

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function emojiMessage(Request $request): \Illuminate\Http\JsonResponse
    {

        DB::beginTransaction();
        try {

            $message_emoji = EmojiMessage::where('message_id',$request->message_id)
                ->where('user_id', $request->user_id)->where('emoji', $request->emoji)->first();


            if($message_emoji != null){

                $message_emoji->delete();

            }else{

                EmojiMessage::where('message_id',$request->message_id)
                    ->where('user_id', $request->user_id)->delete();

                EmojiMessage::create([
                    'user_id' => $request->user_id,
                    'message_id' => $request->message_id,
                    'emoji' => $request->emoji,
                ]);

            }

            // Trigger a new-emoji-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );


            DB::commit();
            $message = Message::with('emojimessages')->find($request->message_id);


            $pusher->trigger('livewire-chat', 'message-emoji', [
                'message' => $message,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteForEveryoneMessage(Request $request): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $message = Message::find($request->message_id)->delete();

            DB::commit();

            $pusher->trigger('livewire-chat', 'delete-for-everyone-message', [
                'status' => 'success',
            ]);

            return response()->json([
                'status' => 'success',
            ], 200);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function deleteForMeMessage(Request $request): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {

            DeletedMessage::create([
                'user_id' => $request->user_id,
                'message_id' => $request->message_id,
                'delete' => true,
            ]);

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            DB::commit();
            $message = Message::with('deletemessages')->find($request->message_id);

            $message_delete = DeletedMessage::with([
                'MessageDeletedMessage' => function ($query){
                    $query->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        },

                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'delete')->
            where('message_id',$request->message_id)
                ->where('user_id', $request->user_id)->latest()->get();

            $pusher->trigger('livewire-chat', 'delete-for-me-message', [
                'message' => $message->id,
                'message_delete' => $message_delete[0] ?? []
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'message_delete' => $message_delete[0] ?? []
            ], 200);

        } catch (\Exception $e) {
            // Return Json Response
            DB::rollBack();
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

}
