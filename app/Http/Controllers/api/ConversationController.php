<?php

namespace App\Http\Controllers\api;

use App\Events\Conversation\CreateGroup;
use App\Events\Conversation\CreatePeer;
use App\Events\Conversation\MuteConversation;
use App\Events\Conversation\UpdateImage;
use App\Events\Conversation\UpdateName;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipantResource;
use App\Models\Conversation;
use App\Models\MacAddress;
use App\Models\MutedConversation;
use App\Models\Participant;
use App\Models\PinnedMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pusher\Pusher;

class ConversationController extends Controller
{
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
                'mutesConversation' => function ($query) {
                    $query->select('user_id', 'conversations_id', 'mute');
                },
                'messages' => function ($query) {
                    $query->orderBy('created_at', 'desc')->with(['MessageUser' => function($query) {
                        $query->orderBy('created_at', 'desc')->select('id', 'full_name', 'image');
                    }])->with(['parent' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['polls' => function($query) {
                        $query->orderBy('created_at', 'desc')->with([
                            'pollVotes' => function($query){
                                $query->orderBy('created_at', 'desc');
                            }
                        ]);
                    }])->with(['starmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['pinmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['emojimessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['deletemessages' => function($query) {
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

                $createdConversation_id = $createdConversation->id;

                DB::commit();

                $conversations = Participant::where('user_id', $request->sender_id)
                    ->where('conversations_id',$createdConversation_id)
                    ->first();


                event(new CreatePeer($conversations));


                return response()->json([
                    'status' => 'success',
                    'conversation' => new ParticipantResource($conversations),
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

    public function creatEncryptionConversation(Request $request){

        DB::beginTransaction();
        try {

            $user_mac = MacAddress::where('user_id', $request->sender_id)
                ->where('mac_address', $request->mac_address)
                ->where('mac_type', $request->mac_type)->get();

            if (count($user_mac) == 0) {
                $mac_address = MacAddress::create([
                    'mac_address' => $request->mac_address,
                    'mac_type' => $request->mac_type,
                    'user_id' => $request->sender_id
                ]);
            }

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
                        $query->orderBy('created_at', 'desc')->with([
                            'pollVotes' => function($query){
                                $query->orderBy('created_at', 'desc');
                            }
                        ]);
                    }])->with(['starmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['pinmessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }])->with(['emojimessages' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    }]);
                }
            ])->where('type', 'private')
                ->where('receiver_id', $request->sender_id)
                ->where('sender_id', $request->receiver_id)
                ->orWhere('receiver_id', $request->receiver_id)
                ->where('type', 'private')
                ->where('sender_id', $request->sender_id)->get();


            $user = User::where('id', $request->receiver_id)->first();

            if (count($checkedConversation) == 0) {

                $createdConversation= Conversation::create([
                    'type' => 'private  ',
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

                $createdConversation_id = $createdConversation->id;

                DB::commit();

                $conversation = Participant::with([
                    'ConversationParticipant' => function($query){
                        $query->orderBy('last_time_message','desc')->with([
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
                                    $query->orderBy('created_at', 'desc')->with([
                                        'pollVotes' => function($query){
                                            $query->orderBy('created_at', 'desc');
                                        }
                                    ]);
                                }])->with(['starmessages' => function($query) {
                                    $query->orderBy('created_at', 'desc');
                                }])->with(['pinmessages' => function($query) {
                                    $query->orderBy('created_at', 'desc');
                                }])->with(['emojimessages' => function($query) {
                                    $query->orderBy('created_at', 'desc');
                                }]);
                            }
                        ]);
                    }
                ])->where('user_id', $request->sender_id)->where('conversations_id',$createdConversation_id)->first();

                event(new CreatePeer($conversation));

//                $pusher->trigger('livewire-chat', 'create-peer-chat', [
//                    'conversation' => $conversation,
//                ]);

                return response()->json([
                    'status' => 'success',
                    'conversation' => $conversation,
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

    public function creatGroupConversation(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            $object = json_decode($request->participants);

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

            foreach($object as $key => $data)
            {
                $participant = Participant::create([
                    'conversations_id' => $group->id,
                    'user_id' => $data
                ]);
            }

            DB::commit();

            $conversations = Participant::where('conversations_id', $group->id)->first();

            event(new CreateGroup($conversations));

            return response()->json([
                'status' => 'success',
                'conversation' => new ParticipantResource($conversations),

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

    public function getConversations(Request $request): JsonResponse
    {

        try {

            $auth_id = $request->user_id;

            $conversations = Participant::where('user_id', $auth_id)->get();

            return response()->json([
                'status' => 'success',
                'conversations' => ParticipantResource::collection($conversations),
//                'conversations' => $conversations
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function getOneConversation(Request $request): JsonResponse
    {

        try {

            $conversations = Participant::where('conversations_id', $request->conversation_id)->first();

            return response()->json([
                'status' => 'success',
                'conversations' => new ParticipantResource($conversations),
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function muteConversation(Request $request): JsonResponse
    {

        DB::beginTransaction();
        try {


            $conversation_muted = MutedConversation::where('conversations_id',$request->conversation_id)
                ->where('user_id', $request->user_id)->first();


            if($conversation_muted != null){

                $conversation_muted->delete();

            }else{

                MutedConversation::create([
                    'user_id' => $request->user_id,
                    'conversations_id' => $request->conversation_id,
                    'mute' => true,
                ]);

            }

            DB::commit();
            $conversation = Conversation::with('mutesConversation')->find($request->conversation_id);

            event(new MuteConversation($conversation));

            return response()->json([
                'status' => 'success',
                'conversations' => $conversation,
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

    public function inviteParticipantConversation(Request $request): JsonResponse
    {

        DB::beginTransaction();
        try {

            $object = json_decode($request->participants);

            foreach($object as $key => $data)
            {
                $participant = Participant::create([
                    'conversations_id' => $request->conversation_id,
                    'user_id' => $data
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

    public function updateNameConversation(Request $request): JsonResponse
    {

        DB::beginTransaction();
        try {

            $conversation = Conversation::find($request->conversation_id);
            $conversation->name = $request->name;
            $conversation->save();

            event(new UpdateName($conversation));

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

    public function updateImageConversation(Request $request): JsonResponse
    {

        DB::beginTransaction();
        try {

            $this->validate($request, [
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:16384',
            ]);
            $imageName = Str::random(32) . "." . $request->image->getClientOriginalExtension();

            if ($request->hasFile('image')) {
                Storage::disk('public')->put('groups/' . $imageName, file_get_contents($request->image));
            }

            $path = 'https://vela-test-chat.pal-lady.com/storage/app/public/groups/' . $imageName;

            $conversation = Conversation::find($request->conversation_id);
            $conversation->image = $path;
            $conversation->save();

            event(new UpdateImage($conversation));

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

    public function pinConversation(Request $request): JsonResponse
    {

        try {

            $pin_message = PinnedMessage::with('MessagePinnedMessage')
                ->where('conversations_id', $request->conversation_id)
                ->where('user_id', $request->user_id)->get();

            return response()->json([
                'status' => 'success',
                'pin_message' => $pin_message[0] ?? NULL,
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function infoGroupConversation(Request $request): JsonResponse
    {

        try {

            $users = Conversation::with(['usersConversation' => function($query){
                $query->with(['UserParticipant' => function($query){
                    $query->select('id', 'full_name', 'email', 'job', 'phone_NO','image');
                }]);
            }])->select('id','type')->find($request->conversation_id);

            $messages = Conversation::with(['messages' => function($query){
                $query->select('id', 'message','conversations_id','is_image','is_file')->where('is_image' , true)->orWhere('is_file', true);
            }])->select('id','type')->find($request->conversation_id);


            return response()->json([
                'status' => 'success',
                'users' => $users,
                'messages' => $messages,
//                'conversations' => $conversations
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function infoPeerConversation(Request $request): JsonResponse
    {

        try {

            $users = Conversation::with(['usersConversation' => function($query){
                $query->with(['UserParticipant' => function($query){
                    $query->select('id', 'full_name', 'email', 'job', 'phone_NO','image');
                }]);
            }])->select('id','type')->find($request->conversation_id);

            $messages = Conversation::with(['messages' => function($query){
                $query->select('id', 'message','conversations_id','is_image','is_file')->where('is_image' , true)->orWhere('is_file', true);
            }])->select('id','type')->find($request->conversation_id);

//            $conversations = Participant::with(['ConversationParticipant' => function($query) use ($request){
//                $query->select('id')->with(['usersConversation' => function($query) use ($request){
//                    $query->where('user_id',$request->user_id);
//                }]);
//            }])->select('id', 'conversations_id')->where('user_id', $request->receiver_id)->get();

            return response()->json([
                'status' => 'success',
                'users' => $users,
                'messages' => $messages,
//                'conversations' => $conversations
            ], 200);


        } catch (\Exception $e) {
            // Return Json Response
            return response()->json([
                'message' => "Something went really wrong!",
                'error' => $e->getMessage()

            ], 500);
        }
    }


}
