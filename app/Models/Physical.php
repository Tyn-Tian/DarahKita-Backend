<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Physical extends Model
{
    protected $table = 'physicals';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'id',
        'systolic',
        'diastolic',
        'pulse',
        'weight',
        'temperatur',
        'hemoglobin'
    ];

    public function donation()
    {
        return $this->belongsTo(Donation::class, 'id', 'physical_id');
    }
}
