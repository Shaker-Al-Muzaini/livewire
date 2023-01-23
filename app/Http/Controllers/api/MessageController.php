<?php

namespace App\Http\Controllers\api;

use App\Events\Conversation\AllConversations;
use App\Events\Message\DeleteMessageForEveryone;
use App\Events\Message\DeleteMessageForMe;
use App\Events\Message\MessageForward;
use App\Events\Message\MessageRead;
use App\Events\Message\PinMessage;
use App\Events\Message\SendMessage;
use App\Events\Message\SendMessageEmoji;
use App\Events\Message\SendMessageFile;
use App\Events\Message\SendMessageImage;
use App\Events\Message\SendMessagePoll;
use App\Events\Message\SendMessageVoice;
use App\Events\Message\SendPollVote;
use App\Events\Message\StarMessage;
use App\Events\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ParticipantResource;
use App\Models\Conversation;
use App\Models\DeletedMessage;
use App\Models\EmojiMessage;
use App\Models\Message;
use App\Models\Participant;
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

            $newMessage = Message::find($message->id);
            $user_id = $request->user_id;
            $message_id = $message->id;
            $conversations_id = $request->conversations_id;

            event(new SendMessage($newMessage, $user_id, $message_id, $conversations_id));

            return response()->json([
                'status' => 'success',
                'message' => new MessageResource($newMessage),
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

            $conversation = Conversation::find($request->conversation_id);

            $messages = $conversation->messages()->where('user_id','!=', $request->user_id)->update([
                'read' => true
            ]);

            $user_id = $request->user_id;
            $conversation_id = $request->conversation_id;
            event(new MessageRead($messages, $user_id, $conversation_id));

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

            $path = 'https://vela-test-chat.pal-lady.com/storage/app/public/images/' . $imageName;


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

            DB::commit();

            $newMessage = Message::find($message->id);
            $user_id = $request->user_id;
            $conversations_id = $request->conversations_id;

            event(new SendMessageImage($newMessage, $user_id, $conversations_id));

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

            $path = 'https://vela-test-chat.pal-lady.com/storage/app/public/files/' . $imageName;


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

            DB::commit();

            $newMessage = Message::find($message->id);
            $user_id = $request->user_id;
            $conversations_id = $request->conversations_id;

            event(new SendMessageFile($newMessage, $user_id, $conversations_id));

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

            $path = 'https://vela-test-chat.pal-lady.com/storage/app/public/voices/' . $imageName;


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

            DB::commit();

            $newMessage = Message::find($message->id);
            $user_id = $request->user_id;
            $conversations_id = $request->conversations_id;

            event(new SendMessageVoice($newMessage, $user_id, $conversations_id));

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

            $user_id = $user->id;
            $status =  $user->status;
            event(new UserStatus($user_id, $status));

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

            event(new PinMessage($message));

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

            event(new StarMessage($message));

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

            $newMessage = Message::find($message->id);
            $user_id = $request->user_id;
            $conversations_id = $request->conversations_id;

            event(new SendMessagePoll($newMessage, $user_id, $conversations_id));

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

                $newMessage = Message::find($poll->message_id);

                event(new SendPollVote($newMessage));

                return response()->json([
                    'status' => 'success',
                    'newMessage' => new MessageResource($newMessage),
                ], 200);

            }else{

                $poll = Poll::find($request->poll_id);
                $poll->rate = $poll->rate - 1;
                $poll->save();

                $polls->delete();

                DB::commit();

                $newMessage = Message::find($poll->message_id);

                event(new SendPollVote($newMessage));

                return response()->json([
                    'status' => 'success',
                    'poll_vote' => new MessageResource($newMessage),
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

            DB::commit();

            $newMessage = Message::find($request->message_id);

            event(new SendMessageEmoji($newMessage));

            return response()->json([
                'status' => 'success',
                'message' => new MessageResource($newMessage),
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

            $message = Message::find($request->message_id)->delete();

            DB::commit();

            $newMessage = Message::withTrashed()->find($request->message_id);

            event(new DeleteMessageForEveryone($newMessage));


            return response()->json([
                'status' => 'success',
                'message' => new MessageResource($newMessage)
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


            DB::commit();
            $message = Message::with('deletemessages')->find($request->message_id);

            $newMessage = Message::find($request->message_id);

            event(new DeleteMessageForMe($newMessage));

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'message_delete' => new MessageResource($newMessage)
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

    public function forwardMessage(Request $request): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {

            foreach ($request->user_id as $key => $user){

                $checkedConversation = Conversation::where('receiver_id', $request->sender_id)
                    ->where('sender_id', $user)
                    ->orWhere('receiver_id', $user)
                    ->where('sender_id', $request->sender_id)->first();

                if(!$checkedConversation){

                    $sender = User::find($request->sender_id);

                    $createdConversation= Conversation::create([
                        'type' => 'peer',
                        'sender_id' => $request->sender_id,
                        'receiver_id' => $user,
                        'company_NO' => $sender->company_NO
                    ]);

                    DB::commit();

                    $participant = Participant::create([
                        'conversations_id' => $createdConversation->id,
                        'user_id' => $request->sender_id
                    ]);

                    $participant2 = Participant::create([
                        'conversations_id' => $createdConversation->id,
                        'user_id' => $user
                    ]);

                    foreach ($request->message_id as $message){

                        $oldMessage = Message::find($message);

                        if($oldMessage->is_image){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $createdConversation->id,
                                'is_forward' => true,
                                'is_image' => true
                            ]);
                        }elseif ($oldMessage->is_file){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $createdConversation->id,
                                'is_forward' => true,
                                'is_file' => true
                            ]);
                        }elseif ($oldMessage->is_voice){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $createdConversation->id,
                                'is_forward' => true,
                                'is_voice' => true
                            ]);
                        }else{
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $createdConversation->id,
                                'is_forward' => true
                            ]);
                        }
                    }

                }else{

                    foreach ($request->message_id as $message){

                        $oldMessage = Message::find($message);

                        if($oldMessage->is_image){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $checkedConversation->id,
                                'is_forward' => true,
                                'is_image' => true
                            ]);
                        }elseif ($oldMessage->is_file){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $checkedConversation->id,
                                'is_forward' => true,
                                'is_file' => true
                            ]);
                        }elseif ($oldMessage->is_voice){
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $checkedConversation->id,
                                'is_forward' => true,
                                'is_voice' => true
                            ]);
                        }else{
                            $newMessage = Message::create([
                                'user_id' => $request->sender_id,
                                'message' => $oldMessage->message,
                                'conversations_id' => $checkedConversation->id,
                                'is_forward' => true
                            ]);
                        }

                    }

                }

            }

            DB::commit();

            $messages = Message::where('is_forward', true)->where('created_at', date('Y-m-d H:i:s'))->get();
            $conversations = Participant::where('user_id', $request->sender_id)->get();

            event(new MessageForward($messages));
//            event(new AllConversations($conversations));

            return response()->json([
                'status' => 'success',
                'messages' => MessageResource::collection($messages),
                'conversations' => ParticipantResource::collection($conversations),
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
