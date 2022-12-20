<?php

namespace App\Http\Livewire\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use GuzzleHttp\Promise\Create;
use Livewire\Component;

class CreateChat extends Component
{
    public $users;
    public $message= 'hello how are you ';


    public function checkconversation($receiverId)
    {


        $checkedConversation = Conversation::where('receiver_id', auth()->user()->id)
            ->where('sender_id', $receiverId)->orWhere('receiver_id', $receiverId)
            ->where('sender_id', auth()->user()->id)->get();


        if (count($checkedConversation) == 0) {


            $createdConversation= Conversation::create(['receiver_id'=>$receiverId,'sender_id' => auth()->user()->id,]);
          /// conversation created
            $createdMessage= Message::create(['conversation_id'=>$createdConversation->id,'sender_id'=>auth()->user()->id,'receiver_id'=>$receiverId,'body'=>$this->message]);


        $createdConversation->last_time_message= $createdMessage->created_at;
        $createdConversation->save();

        dd($createdMessage);
        dd('saved');




        } else if (count($checkedConversation) >= 1) {

            dd(
                'conversation exists'
            );
        }
        # code...
    }

    public function render()
    {


        $this->users = User::where('id','!=',auth()->user()->id)->where('company_NO',auth()->user()->company_NO)->orderBy('full_name','asc')->get();
        return view('livewire.chat.create-chat');
    }

}
