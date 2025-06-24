<?php

namespace App\Http\Controllers\Helpers;

use Illuminate\Support\Facades\DB;

/**
     ***** THIS FILE CONTAINS ALL THE BASIC FUNCITONS WHICH ARE USED IN DIFFERENT PARTS OF THE API *****
     */
    
class BasicFunctions
{
    /**
     * Get year ID from the years table.
     */
    public static function getYearId($year = null)
    {
        $year = $year ?? date('Y');

        return DB::table('years')
            ->where('name', $year)
            ->value('id');
    }

    /**
     * Get table suffix like "6_12" (month_yearId)
     */
    public static function getMonthYearSuffix()
    {
        $month = date('n'); // 1–12
        $yearId = self::getYearId();

        return $yearId ? "{$month}_{$yearId}" : null;
    }
}
