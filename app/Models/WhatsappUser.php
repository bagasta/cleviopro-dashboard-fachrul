<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappUser extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_user';

    protected $fillable = [
        'user_id',
        'agent_id',
        'api_key',
        'session_name',
        'endpoint_url_run',
        'status',
        'last_connected_at',
        'last_disconnected_at',
    ];

    protected $casts = [
        'user_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'last_disconnected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessionStatus()
    {
        return $this->hasOne(WhatsappSessionStatus::class, 'agent_id', 'agent_id');
    }
}
