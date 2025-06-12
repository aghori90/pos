<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;

class AlertMessage extends Model
{
    protected $table = 'alertMessage';
    public $timestamps = false;

    protected $fillable = ['status'];
}
