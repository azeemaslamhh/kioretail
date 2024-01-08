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
        Schema::create('tasks', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('record_id')->nullable();            
            $table->string('task', 191)->nullable();;                 
            $table->enum('type', ['query','command'])->default("command");
            $table->tinyInteger('status')->default(0);
            $table->text('value')->nullable();         
            $table->longText('data')->nullable();         
            $table->tinyInteger('attempts')->default(3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
