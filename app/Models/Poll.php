<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;

    protected $fillable=[
//        'user_id',
        'message_id',
        'poll_options',
    ];

    public function PollMessage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id', 'id');
    }

//    public function PollUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
//    {
//        return $this->belongsTo(User::class, 'user_id', 'id');
//    }

}
