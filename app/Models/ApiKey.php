<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_key';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'key_hash',
        'label',
        'active',
        'expires_at',
        'created_at',
        'last_used_at',
    ];

    protected $casts = [
        'user_id' => 'int',
        'active' => 'bool',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
