<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $table = 'donations';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'donor_id',
        'donor_schedule_id',
        'blood_stock_id',
    ];

    public function donorSchedule()
    {
        return $this->belongsTo(DonorSchedule::class);
    }

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }

    public function pmiCenter()
    {
        return $this->belongsTo(PmiCenter::class);
    }
}
