<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;

class HhdMaster extends Model
{
    protected $table = 'hhd_masters';
    public $timestamps = false;
    protected $fillable = ['version_id', 'signalRange', 'mobileOperaterName', 'simNumber', 'lastLogin'];
}
