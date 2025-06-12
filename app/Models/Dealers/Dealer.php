<?php

namespace App\Models\Dealers;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Dealer extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $customTable = null;

    protected $fillable = [
        'id', 'dealerType', 'tagDealerId', 'active', 'weighingFlag', 'dongleFlag'
    ];

    public function setCustomTable($table)
    {
        $this->customTable = $table;
        return $this;
    }

    public function getTable()
    {
        if ($this->customTable) {
            return $this->customTable;
        }

        $monthId = now()->month;
        $year = now()->year;

        // Fetch yearId from `years` table
        $yearId = DB::table('years')->where('name', $year)->value('id');

        if (!$yearId) {
            throw new \Exception("No matching yearId found for year $year in `years` table.");
        }

        return "hhd_{$monthId}_{$yearId}_dealers";
    }
}
