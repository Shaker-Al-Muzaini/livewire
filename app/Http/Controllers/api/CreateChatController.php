<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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

class CreateChatController extends Controller
{
    //

    public function creatConversation(Request $request){

        DB::beginTransaction();
        try {

            $checkedConversation = Conversation::with([
                'SenderConversation' => function ($query) {
                    $query->select('id', 'full_name', 'image');
                },
                'ReceiverConversation' => function ($query) {
                    $query->select('id', 'full_name', 'image');
                },
                'messages' => function ($query) {
                    $query->orderBy('created_at', 'desc')->with(['MessageUser' => function($query) {
                        $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                    }])->with(['parent' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['polls' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['starmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['pinmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }]);
                }
            ])
                ->where('receiver_id', $request->sender_id)
                ->where('sender_id', $request->receiver_id)
                ->orWhere('receiver_id', $request->receiver_id)
                ->where('sender_id', $request->sender_id)->get();


            $user = User::where('id', $request->receiver_id)->first();

            if (count($checkedConversation) == 0) {

                $createdConversation= Conversation::create([
                    'type' => 'peer',
                    'sender_id' => $request->sender_id,
                    'receiver_id' => $request->receiver_id,
                    'company_NO' => $user->company_NO
                ]);

                $participant = Participant::create([
                    'conversations_id' => $createdConversation->id,
                    'user_id' => $request->sender_id
                ]);

                $participant2 = Participant::create([
                    'conversations_id' => $createdConversation->id,
                    'user_id' => $request->receiver_id
                ]);


                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'createdConversation' => $createdConversation,
                ], 200);

            } else if (count($checkedConversation) >= 1) {

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'checkedConversation' => $checkedConversation,
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

    public function creatGroupConversation(Request $request){

        DB::beginTransaction();
        try {

            $user = User::find($request->user_id);

            $group = Conversation::create([
               'name' => $request->group_name,
               'type' => 'group',
               'admin_id' => $user->id,
                'company_NO' => $user->company_NO
            ]);

            Participant::create([
                'conversations_id' => $group->id,
                'user_id' => $user->id
            ]);

            $object = json_decode($request->participants);

            foreach($object as $key => $data)
            {
                $participant = Participant::create([
                    'conversations_id' => $group->id,
                    'user_id' => $data->participants
                ]);
            }
            DB::commit();
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

    public function getConversations(Request $request)
    {

        try {

            $auth_id = $request->user_id;

            $conversations = Conversation::with(

                [
                    'usersConversation' => function ($query) {
                        $query->orderBy('created_at', 'desc')->with(['UserParticipant' => function($query) {
                            $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                        }]);
                    },
                    'SenderConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'ReceiverConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'messages' => function ($query) {
                        $query->orderBy('created_at', 'desc')->with(['MessageUser' => function($query) {
                            $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                        }])->with(['parent' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['polls' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['starmessages' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['pinmessages' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }]);
                    }
                ]

            )->orderBy('last_time_message','desc')->where('sender_id', $auth_id)
                ->orWhere('receiver_id', $auth_id)->orWhere('admin_id', $auth_id)->
                get();

//            $conversations = Conversation::with('usersConversation')->where('sender_id', $auth_id)
//                ->orWhere('receiver_id', $auth_id)->get();

            return response()->json([
                'status' => 'success',
                'conversations' => $conversations,
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function getOneConversation(Request $request)
    {

        try {

            $conversation = Conversation::with(

                [
                    'usersConversation' => function ($query) {
                    $query->orderBy('created_at', 'desc')->with(['UserParticipant' => function($query) {
                        $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                    }]);
                    },
                    'SenderConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'ReceiverConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'messages' => function ($query) {
                        $query->orderBy('created_at', 'desc')->with(['MessageUser' => function($query) {
                            $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                        }])->with(['parent' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['polls' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['starmessages' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }])->with(['pinmessages' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }]);
                    }
                ]

            )->orderBy('last_time_message','desc')
                ->where('id',$request->conversation_id)->first();

//            $conversations = Conversation::with('usersConversation')->where('sender_id', $auth_id)
//                ->orWhere('receiver_id', $auth_id)->get();

            return response()->json([
                'status' => 'success',
                'conversation' => $conversation,
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }


    public function sendMessage(Request $request){

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

            $pusher->trigger('livewire-chat', 'message-sent', [
                'user_id' => $request->user_id,
                'message_id' => $message->id,
                'message' => $newMessage,
                'conversations_id' => $request->conversations_id,
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


    public function readMessage(Request $request){

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
                'user_id' => $request->user_id
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

    public function submitImage(Request $request)
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
                'is_image' => true
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
                $query->orderBy('created_at', 'desc');
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-image-message', [
                'message' => $newMessage,
                'conversation_id' => $request->conversation_id,
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

    public function submitFile(Request $request)
    {

        DB::beginTransaction();
        try {
            // Validate the request data
            $this->validate($request, [
                'file' => 'file|max:16384',
            ]);

            $file = $request->file('file');

            $name = $file->getClientOriginalName();

            // If a file was uploaded, store it in the file system or cloud storage
            if ($request->hasFile('file')) {
                Storage::disk('public')->put('files/' . $name, file_get_contents($request->file));
            }

            $path = 'http://vela-test-chat.pal-lady.com/storage/app/public/files/' . $name;


            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $path,
                'conversations_id' => $request->conversations_id,
                'is_file' => true
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
                $query->orderBy('created_at', 'desc');
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-file-message', [
                'message' => $newMessage,
                'conversation_id' => $request->conversation_id,
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

            $voice = $request->file('file');

            $name = $voice->getClientOriginalName();

            // If a voice was uploaded, store it in the file system or cloud storage
            if ($request->hasFile('file')) {
                Storage::disk('public')->put('voices/' . $name, file_get_contents($request->$voice));
            }

            $path = 'http://vela-test-chat.pal-lady.com/storage/app/public/voices/' . $name;


            $message = Message::create([
                'user_id' => $request->user_id,
                'message' => $path,
                'conversations_id' => $request->conversations_id,
                'is_voice' => true
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
                $query->orderBy('created_at', 'desc');
            }])->with(['MessageUser' => function ($query) {
                $query->select('id', 'full_name', 'image');
            }])->with(['starmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->with(['pinmessages' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->find($message->id);

            $pusher->trigger('livewire-chat', 'new-voice-message', [
                'message' => $newMessage,
                'conversation_id' => $request->conversation_id,
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

            // Trigger a message-pinned event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $pusher->trigger('livewire-chat', 'message-pinned', [
                'message' => $message->id,
            ]);

            return response()->json([
                'status' => 'success',
                '   message' => $message
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

            // Trigger a new-image-message event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );


            DB::commit();
            $message = Message::with('starmessages')->find($request->message_id);


            $pusher->trigger('livewire-chat', 'message-starred', [
                'message' => $message->id,
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
                    $query->select('id','user_id', 'conversations_id')->with([
                        'MessageUser' => function ($query) {
                            $query->select('id', 'full_name', 'image');
                        }
                    ]);
                },
            ])->select('id', 'message_id', 'user_id', 'star')->where('user_id',$request->user_id)->latest()->get();

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



    public function createReplay(Request $request): \Illuminate\Http\JsonResponse
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


            $pusher->trigger('livewire-chat', 'message-replay', [
                'parent_id' => $request->parent_id,
                'user_id' => $request->user_id,
                'message_id' => $message->id,
                'message_body' => $request->message,
                'conversations_id' => $request->conversations_id,
            ]);

            DB::commit();
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

    public function createPoll(Request $request): \Illuminate\Http\JsonResponse
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
                'is_poll' => true,
            ]);

            $conversation = Conversation::where('id', $request->conversations_id)->update([
                'last_time_message' => $message->created_at
            ]);

            $object = json_decode($request->poll_options);

            foreach($object as $key => $data)
            {
                $poll_options = Poll::create([
                    'message_id' => $message->id,
                    'poll_options' => $data->poll_options
                ]);
            }

            $pusher->trigger('livewire-chat', 'message-poll', [
                'user_id' => $request->user_id,
                'poll_options' => $request->poll_options,
                'message' => $message,
                'conversations_id' => $request->conversations_id,
            ]);

            DB::commit();
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

                $new = PollVote::with(['PollVotePoll' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])->with(['PollVoteUser' => function ($query) {
                    $query->select('id', 'full_name', 'image');
                }])->find($poll_vote->id);


                $pusher->trigger('livewire-chat', 'poll-vote', [
                    'poll_vote' => $new,
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

                $pusher->trigger('livewire-chat', 'poll-vote', [
                    'poll_vote' => 'success',
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


}
