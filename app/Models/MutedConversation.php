<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutedConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversations_id',
        'mute'
    ];

    public function ConversationParticipant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversations_id', 'id');
    }

    public function UserParticipant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
