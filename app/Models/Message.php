<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{

    use HasFactory;
    use SoftDeletes;

    protected $connection = 'mysql';


    protected $fillable=[
        'user_id',
        'conversations_id',
        'message',
//        'emoji',
        'read',
        'is_image',
        'is_file',
        'is_voice',
        'is_poll',
        'parent_id'
    ];

    public function MessageConversation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversations_id', 'id');
    }

    public function MessageUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function polls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function starmessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StarredMessage::class);
    }

    public function pinmessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PinnedMessage::class);
    }

    public function emojimessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmojiMessage::class);
    }

    public function deletemessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeletedMessage::class);
    }

}
