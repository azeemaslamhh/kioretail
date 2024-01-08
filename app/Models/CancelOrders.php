<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancelOrders extends Model
{
    protected $table = 'cancel_orders';
    protected $fillable =[
        "product_id", "sale_id"
    ];
}
