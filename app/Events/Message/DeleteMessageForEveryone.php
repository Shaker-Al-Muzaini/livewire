<?php

namespace App\Events\Message;

use App\Http\Resources\MessageResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeleteMessageForEveryone implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $newMessage;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($newMessage)
    {
        $this->newMessage =  $newMessage;
    }

    public function broadcastAs(): string
    {
        return 'delete-for-me-message';
    }

    public function  broadcastWith()
    {
        return [
            'status' => 'success',
            'message'=>  new MessageResource($this->newMessage),
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
