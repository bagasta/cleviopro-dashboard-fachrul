<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $table = 'channel';

    protected $fillable = [
        'user_id',
        'agent_id',
        'channel_name',
        'session_name',
        'qr',
        'status',
        'webhook',
    ];

    protected $casts = [
        'user_id' => 'int',
        'agent_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
