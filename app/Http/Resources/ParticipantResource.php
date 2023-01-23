<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "conversations_id" => $this->conversations_id,
            "deleted_at" => $this->deleted_at,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            'conversation_participant' => ConversationResource::make($this->ConversationParticipant)

        ];
    }
}
