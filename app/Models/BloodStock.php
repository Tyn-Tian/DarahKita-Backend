<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodStock extends Model
{
    protected $table = 'blood_stocks';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'id',
        'blood_type',
        'rhesus',
        'quantity',
        'pmi_center_id',
    ];

    public function pmiCenter()
    {
        return $this->belongsTo(PmiCenter::class);
    }
}
