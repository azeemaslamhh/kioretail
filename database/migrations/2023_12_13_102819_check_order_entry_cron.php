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
        DB::statement("INSERT INTO `user_cron_settings` (`id`, `cron_name`, `cron_value`, `cron_time`, `created_at`, `updated_at`) VALUES (NULL, 'check:orders', '15', NULL, '2023-10-25 05:20:37', '2023-10-25 05:20:37');");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
