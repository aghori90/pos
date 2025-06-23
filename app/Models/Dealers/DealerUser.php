<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Dealers\Dealer;

class DealerUser extends Model
{
    protected $table = 'dealer_users';
    public $timestamps = true;

    protected $fillable = [
        'uid', 'uidVaultFlag', 'vault_token', 'dealer_id',
        'f_name', 'l_name', 'block_city_id', 'district_id'
    ];

    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }
}
