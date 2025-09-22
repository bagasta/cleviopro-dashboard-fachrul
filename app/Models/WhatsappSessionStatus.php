<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSessionStatus extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_session_status';

    protected $primaryKey = 'agent_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'status',
        'last_connected_at',
        'last_disconnected_at',
        'updated_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'last_disconnected_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function whatsappUser()
    {
        return $this->belongsTo(WhatsappUser::class, 'agent_id', 'agent_id');
    }
}
