<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonorSchedule extends Model
{
    protected $table = 'donor_schedules';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'id',
        'date',
        'location',
        'time',
        'pmi_center_id',
    ];

    public function pmiCenter()
    {
        return $this->belongsTo(PmiCenter::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
