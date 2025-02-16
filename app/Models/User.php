<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'id',
        'name',
        'email',
        'role',
        'password',
        'phone',
        'city',
        'address'
    ];

    protected $hidden = [
        'password',
    ];

    public function pmiCenter()
    {
        return $this->hasOne(PmiCenter::class);
    }

    public function donor()
    {
        return $this->hasOne(Donor::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
