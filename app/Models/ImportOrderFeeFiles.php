<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportOrderFeeFiles extends Model
{
    protected $fillable = ["freight_charges","import_order_fee_files","fuel_surcharge","fuel_factor", "gst", "insurance","file_name","status","message"];
}
