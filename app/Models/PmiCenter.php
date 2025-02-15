<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmiCenter extends Model
{
    protected $table = 'pmi_centers';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'location',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bloodStocks()
    {
        return $this->hasMany(BloodStock::class);
    }

    public function broadcasts()
    {
        return $this->hasMany(Broadcast::class);
    }

    public function bloodRequests()
    {
        return $this->hasMany(BloodRequest::class);
    }

    public function donorSchedules()
    {
        return $this->hasMany(DonorSchedule::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
