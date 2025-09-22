<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'phone_number',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function listAccounts()
    {
        return $this->hasMany(ListAccount::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function whatsappUsers()
    {
        return $this->hasMany(WhatsappUser::class);
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
