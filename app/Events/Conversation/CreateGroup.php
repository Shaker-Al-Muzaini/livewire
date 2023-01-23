<?php

namespace App\Events\Conversation;

use App\Http\Resources\ParticipantResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreateGroup implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversations;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($conversations)
    {
        $this->conversations =  $conversations;
    }

    public function broadcastAs(): string
    {
        return 'create-group';
    }

    public function  broadcastWith()
    {
        return [
            'conversation' =>  new ParticipantResource($this->conversations),
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
