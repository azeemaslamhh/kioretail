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
        Schema::create('import_order_fee_files', function (Blueprint $table) {
            $table->id();            
            $table->integer('courier_id')->nullable();            
            $table->double('fuel_surcharge')->default(0)->nullable();            
            $table->double('fuel_factor')->default(0)->nullable();
            $table->double('gst')->default(0)->nullable();
            $table->double('insurance')->default(0)->nullable();
            $table->string('file_name')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });
        //0 need to execute
        //1 is running
        // 2 completed
        // 4 Error
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::dropIfExists('import_order_fee_files');
    }
};
