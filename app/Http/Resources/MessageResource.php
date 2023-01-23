<?php

namespace App\Http\Resources;

use App\Models\ForwardMessage;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "conversations_id" => $this->conversations_id,
            "message" => $this->message,
            "read" => $this->read,
            "is_image" => $this->is_image,
            "is_file" => $this->is_file,
            "is_voice" => $this->is_voice,
            "is_poll" => $this->is_poll,
            "is_forward" => $this->is_forward,
            "parent_id" => $this->parent_id,
            "deleted_at" => $this->deleted_at,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "message_user" => UserResource::make($this->MessageUser),
            "parent" => $this->parent,
            "polls" => PollResource::collection($this->polls),
            "starmessages" => $this->starmessages,
            "pinmessages" => $this->pinmessages,
            "emojimessages" => $this->emojimessages,
            "deletemessages" => $this->deletemessages,
        ];
    }
}
