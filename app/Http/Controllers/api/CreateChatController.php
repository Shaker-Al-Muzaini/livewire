<?php          
    
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'messages'
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

    public function getConversations(Request $request)
    {

        try {

            $auth_id = $request->user_id;

            $conversations = Conversation::with(

                [
                    'SenderConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'ReceiverConversation' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },
                    'messages' => function ($query) {
                        $query->orderBy('created_at', 'desc');
                    }
                ]

            )->orderBy('last_time_message','desc')->where('sender_id', $auth_id)
                ->orWhere('receiver_id', $auth_id)->
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


            $pusher->trigger('livewire-chat', 'MessageSent', [
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


    public function readMessage(Request $request){

        DB::beginTransaction();
        try {

//            $conversation = Conversation::with('messages')->where('id', $request->conversation_id)->get();
            $conversation = Conversation::find($request->conversation_id);
            $messages = $conversation->messages()->where('user_id','!=', $request->user_id)->update([
                'read' => true
            ]);

            DB::commit();
            return response()->json([
                'status' => 'success',
                'messages' => $messages
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

}
