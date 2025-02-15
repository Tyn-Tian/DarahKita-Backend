<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodRequest extends Model
{
    protected $table = 'blood_requests';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'blood_type',
        'blood_volume',
        'blood_needed',
        'blood_stock_id',
        'user_id',
    ];

    public function pmiCenter()
    {
        return $this->belongsTo(PmiCenter::class);
    }

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }
}
