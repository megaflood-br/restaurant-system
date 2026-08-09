<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'preparing', 'ready', 'served', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE orders SET status = 'delivered' WHERE status = 'served'");
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'preparing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
