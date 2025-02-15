<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;   
    public $timestamps = true;
    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'city',
    ];

    public function pmiCenter()
    {
        return $this->hasOne(PmiCenter::class);
    }

    public function donor()
    {
        return $this->hasOne(Donor::class);
    }
}
