<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{

    use HasFactory;

    protected $fillable=[
        'user_id',
        'conversations_id',
        'message',
        'emoji',
        'read',
        'star',
        'pin',
        'parent_id'
    ];


//    public function conversation()
//    {
//        return $this->belongsTo(Conversation::class);
//        # code...
//    }
//
//    public function user( )
//    {
//        return $this->belongsTo(User::class ,'sender_id');
//        # code...
//    }

    public function MessageConversation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversations_id', 'id');
    }

    public function MessageUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

}
