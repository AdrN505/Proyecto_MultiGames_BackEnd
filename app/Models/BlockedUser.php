<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'blocked_user_id',
    ];

    /**
     * El usuario que bloqueó
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El usuario que fue bloqueado
     */
    public function blockedUser()
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}