<?php

namespace App\Events\Message;

use App\Http\Resources\MessageResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageForward implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messages;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($messages)
    {
        $this->messages =  $messages;
    }

    public function broadcastAs(): string
    {
        return 'forward-message';
    }

    public function  broadcastWith()
    {
        return [
            "status" => 'success',
            'message'=>  MessageResource::collection($this->messages),
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
        return new Channel('livewire-chat');
    }
}
