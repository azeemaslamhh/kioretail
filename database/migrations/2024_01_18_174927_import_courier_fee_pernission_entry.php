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
        $date = date("Y-m-d H:i:s");
        DB::statement("INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (NULL, 'import_couriers_fee', 'web', '".$date."', '".$date."');");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Db::table("permissions")->where('name','import_couriers_fee')->delete();
    }
};
