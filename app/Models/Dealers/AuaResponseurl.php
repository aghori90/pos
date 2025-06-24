<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;

class AuaResponseurl extends Model
{
    protected $table = 'auaresponseurls';

    public $timestamps = true;

    protected $fillable = [
        'sever_ip',
        'status',
        'aua_url',
        'auaUrlFlag'
    ];
}
