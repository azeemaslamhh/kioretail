<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {                        
            $table->tinyInteger('is_locked')->after('is_shipping_free')->default(0)->nullable();
            $table->double("courier_fee")->after('is_shipping_free')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn('is_shipping_free');
            $table->dropColumn('courier_fee');
        });
    }
};
