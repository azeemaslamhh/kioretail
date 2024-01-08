<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronjobLogs extends Model
{
    protected $fillable = ['cron', 'datetime'];

}
