<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messages;
    public $user_id;
    public $conversation_id;

    public function __construct($messages,$user_id,$conversation_id)
    {

        $this->messages= $messages;
        $this->user_id= $user_id;
        $this->conversation_id= $conversation_id;

        //
    }

    public function broadcastAs(): string
    {
        return 'message-read';
    }

    public function  broadcastWith()
    {

         return [
                'messages' => $this->messages,
                'conversation_id' => $this->conversation_id,
                'user_id' => $this->user_id,
         ];
    }
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {

//        return new PrivateChannel('video-chat');
        return new Channel('video-chat');


    }
}
