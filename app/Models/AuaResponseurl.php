<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuaResponseurl extends Model
{
    protected $table = 'auaResponseurls';

    public $timestamps = true;

    protected $fillable = [
        'aua_url','auaUrlFlag','sever_ip','status','nic_japit'
    ];
}
