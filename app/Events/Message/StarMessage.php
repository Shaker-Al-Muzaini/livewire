<?php

namespace App\Events\Message;

use App\Models\StarredMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StarMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct( $message)
    {
        $this->message =  $message;
    }

    public function broadcastAs(): string
    {
        return 'message-starred';
    }

    public function  broadcastWith()
    {

        $message_star = StarredMessage::with([
            'MessageStarredMessage' => function ($query){
                $query->with([
                    'MessageUser' => function ($query) {
                        $query->select('id', 'full_name', 'image');
                    },

                ]);
            },
        ])->select('id', 'message_id', 'user_id', 'star')->
        where('message_id',$this->message->id)->latest()->get();

        return [
            'message' => $this->message->id,
            'message_star' => $message_star[0] ?? []
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
