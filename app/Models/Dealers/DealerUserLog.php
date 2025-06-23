<?php

namespace App\Models\Dealers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DealerUserLog extends Model
{    
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $customTable = null;

    protected $fillable = [
        'uid', 'hhdSlNo', 'dealer_id', 'dealer_user_id',
        'intime', 'status', 'group_id', 'session_id',
        'block_city_id', 'district_id', 'dealerUserName',
        'dealerName', 'rabbitMqServerId'
    ];

    /**
     * Optionally set a custom table name.
     */
    public function setCustomTable($table)
    {
        $this->customTable = $table;
        return $this;
    }

    /**
     * Dynamically resolve table name based on yearId from years table.
     */
    public function getTable()
    {
        if ($this->customTable) {
            return $this->customTable;
        }

        $monthId = now()->format('m');
        $year = now()->year;

        // Fetch yearId using the 'name' column (assuming it's a string like "2025")
        $yearId = DB::table('years')->where('name', $year)->value('id');

        if (!$yearId) {
            throw new \Exception("No matching yearId found for year $year in `years` table.");
        }

        return "dealerUserLogs_{$monthId}_{$yearId}_backups";
    }
}