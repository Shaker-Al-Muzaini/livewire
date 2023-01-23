<?php

namespace App\Events\Message;

use App\Http\Resources\MessageResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendMessagePoll  implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $newMessage;
    public $user_id;
    public $conversations_id;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($newMessage, $user_id, $conversations_id)
    {
        $this->newMessage =  $newMessage;
        $this->user_id =  $user_id;
        $this->conversations_id =  $conversations_id;
    }

    public function broadcastAs(): string
    {
        return 'message-poll';
    }

    public function  broadcastWith()
    {
        return [
            'message'=>  new MessageResource($this->newMessage),
            "conversations_id" => $this->conversations_id,
            "user_id" => $this->user_id,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): Channel|PrivateChannel|array
    {
//        return new PrivateChannel('video-chat');
        return new Channel('video-chat');
    }
}
