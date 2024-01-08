<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Model;

class UserCronSetting extends Model
{
    public static function getCronTime($name)
    {
        $time = false;
        $cron_value = DB::table("user_cron_settings")->where("cron_name" , $name)->value("cron_value");
        return $cron_value;
        // if($cron_value != 0 or $cron_value == -1)
           
        // return $time;
    }
}
