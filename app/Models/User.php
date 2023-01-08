<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */


        protected $connection = 'mysql_2';
        protected $table = 'users';



    protected $fillable = [
        'full_name',
        'email',
        'password',
        'job',
        'phone_NO',
        'phone_NO2',
        'phone_NO3',
        'image',
        'company_NO',
        'company_name',
        'role_id',
        'status',
        'private_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = [
        'avatar_url',
    ];

    public function UserSConversation(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Conversation::class,'sender_id','id');
    }

    public function macAddresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MacAddress::class,'user_id','id');
    }

    public function UserRConversation(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Conversation::class,'receiver_id','id');
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class,'user_id','id');
    }

    public function stars(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StarredMessage::class,'user_id','id');
    }

    public function pins(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PinnedMessage::class,'user_id','id');
    }

    public function emojis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmojiMessage::class,'user_id','id');
    }

    public function mutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MutedConversation::class,'user_id','id');
    }

    public function deletes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeletedMessage::class,'user_id','id');
    }

    public function pollsVotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PollVote::class,'user_id','id');
    }

    public function participants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Participant::class,'user_id','id');
    }

    public function getAvatarUrlAttribute()
    {
        return 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . $this->name;
    }

}
