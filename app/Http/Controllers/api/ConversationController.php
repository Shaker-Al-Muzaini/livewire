<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\ParticipantResource;
use App\Models\Conversation;
use App\Models\MacAddress;
use App\Models\Message;
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

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

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
                                }]);
                            }
                        ]);
                    }
                    ])->where('user_id', $request->sender_id)->where('conversations_id',$createdConversation_id)->first();

                $pusher->trigger('livewire-chat', 'create-peer-chat', [
                    'conversation' => $conversation,
                ]);

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

    public function creatEncryptionConversation(Request $request){

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

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
                ])->where('user_id', $request->sender_id)->get();

                $pusher->trigger('livewire-chat', 'create-peer-chat', [
                    'conversation' => $conversation,
                ]);

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

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

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
                            }])->with(['deletemessages' => function($query) {
                                $query->orderBy('created_at', 'desc');
                            }]);
                        }
                    ]);
                }
            ])->where('conversations_id', $group->id)->first();

            $pusher->trigger('livewire-chat', 'create-group', [
                'conversation' => $conversation,
            ]);

            return response()->json([
                'status' => 'success',
                'conversation' => $conversation,

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

            $conversations = Participant::with([
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
                    ]);
                }
            ])->where('user_id', $auth_id)->get();

            $conversations = Participant::with('ConversationParticipant')->where('user_id', $auth_id)->get();

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

    public function getOneConversation(Request $request)
    {

        try {


            $conversations = Participant::with([
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
                    ]);
                }
            ])->where('conversations_id', $request->conversation_id)->first();

//            $user = MacAddress::where('user_id', $conversations->ConversationParticipant->SenderConversation->id)
//            ->where('mac_address', $request->mac_address)->get();
//
//            if(count($user) != 0){
//                Message::where('conversation_id', $request->conversation_id)->delete();
//            }

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

            // Trigger a new-mute-conversation event to Pusher
            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );


            DB::commit();
            $conversation = Conversation::with('mutesConversation')->find($request->conversation_id);


            $pusher->trigger('livewire-chat', 'conversation-mute', [
                'conversation' => $conversation->id,
            ]);

            return response()->json([
                'status' => 'success',
                'conversation' => $conversation,
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

    public function inviteParticipantConversation(Request $request){

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

    public function updateNameConversation(Request $request){

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

            $conversation = Conversation::find($request->conversation_id);
            $conversation->name = $request->name;
            $conversation->save();

            $pusher->trigger('livewire-chat', 'update-name-group-chat', [
                'conversation' => $conversation,
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

    public function updateImageConversation(Request $request){

        DB::beginTransaction();
        try {

            $pusher = new Pusher(
                '6b9ab9b8a817a7857923',
                '2189a62314214f15c216',
                '1526965',
                array('cluster' => 'mt1')
            );

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

            $pusher->trigger('livewire-chat', 'update-image-group-chat', [
                'conversation' => $conversation,
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

    public function pinConversation(Request $request)
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

}
