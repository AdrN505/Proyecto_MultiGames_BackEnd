<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
    ];

    /**
     * El usuario que inició la solicitud de amistad
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El usuario que recibió la solicitud de amistad
     */
    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}