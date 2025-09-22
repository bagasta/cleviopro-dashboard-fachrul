<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $table = 'agent';

    protected $fillable = [
        'user_id',
        'nama_model',
        'system_message',
        'tools',
        'agent_type',
        'agent_name',
    ];

    protected $casts = [
        'user_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function listAccounts()
    {
        return $this->hasMany(ListAccount::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function whatsappUser()
    {
        return $this->hasOne(WhatsappUser::class, 'agent_id', 'id');
    }
}
