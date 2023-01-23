<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $messages = MessageResource::collection($this->messages()->latest()->paginate(15));

        $result=[
            'messages'=>  $messages,
            'pagination'=>[
                "i_items_on_page"=> $messages->count(),
                "i_per_pages"=>$messages->perPage(),
                "i_current_page"=>$messages->currentPage() ,
                "i_total_pages"=> $messages->total()
            ]
        ];

        return [
            "id"=> $this->id,
            "name"=> $this->name,
            "image"=> $this->image,
            "type"=> $this->type,
            "last_time_message"=> $this->last_time_message,
            "admin_id"=> $this->admin_id,
            "sender_id"=> $this->sender_id,
            "receiver_id"=> $this->receiver_id,
            "company_NO"=> $this->company_NO,
            "deleted_at"=> $this->deleted_at,
            "created_at"=> $this->created_at,
            "updated_at"=> $this->updated_at,
            "users_conversation"=> UsersConversationResource::collection($this->usersConversation),
            "sender_conversation" => UserResource::make($this->SenderConversation),
            "receiver_conversation" => UserResource::make($this->ReceiverConversation),
            "mutes_conversation" => MuteConversationResource::collection($this->mutesConversation),
            "messages"=>$result,
        ];
    }
}
