<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListAccount extends Model
{
    use HasFactory;

    protected $table = 'list_account';

    protected $fillable = [
        'user_id',
        'agent_id',
        'email',
        'servicesaccount',
    ];

    protected $casts = [
        'user_id' => 'int',
        'agent_id' => 'int',
        'servicesaccount' => 'array',
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
