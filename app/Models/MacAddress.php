<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MacAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable=[
        'user_id',
        'mac_address',
        'mac_type',
    ];

    public function UserMacAddress(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
