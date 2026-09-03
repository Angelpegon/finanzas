<?php

namespace App\Models;

use App\Services\CatalogoInicialService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['nombre', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            app(CatalogoInicialService::class)->sembrar($user);
        });
    }
}
