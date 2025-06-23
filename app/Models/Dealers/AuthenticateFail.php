<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;

class AuthenticateFail extends Model
{
    protected $table = 'authenticate_fails';
    public $timestamps = true;

    protected $fillable = ['uid', 'hhdUniqueId', 'sessionId', 'nooffail'];
}
