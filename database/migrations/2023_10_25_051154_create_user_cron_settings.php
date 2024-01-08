<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_cron_settings')) {
            Schema::create('user_cron_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('cron_name', 55)->nullable();
                $table->integer('cron_value')->nullable();
                $table->string('cron_time', 20)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            });

            DB::statement("INSERT INTO `user_cron_settings` (`id`, `cron_name`, `cron_value`) VALUES
                (1, 'sync_categories', 120),
                (2, 'sync_products', 120),
                (3, 'sync_orders', 120);");
            }
        else {
            if (!Schema::hasColumn('user_cron_settings', 'id')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                    $table->bigIncrements('id');
                });
            }
            if (!Schema::hasColumn('user_cron_settings', 'cron_name')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                    $table->string('cron_name', 55)->nullable();
                });
            }
            if (!Schema::hasColumn('user_cron_settings', 'cron_value')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                    $table->integer('cron_value')->nullable();
                });
            }
            if (!Schema::hasColumn('user_cron_settings', 'cron_time')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                    $table->string('cron_time', 20)->nullable();
                });
            }
            if (!Schema::hasColumn('user_cron_settings', 'created_at')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                    $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
                });
            }
             if (!Schema::hasColumn('user_cron_settings', 'updated_at')) {
                Schema::table('user_cron_settings', function (Blueprint $table) {
                   $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->nullable()->useCurrent();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cron_settings');
    }
};
