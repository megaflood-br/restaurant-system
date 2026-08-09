<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('waiter')->after('email');
        });

        if (Schema::hasTable('users')) {
            DB::table('users')->where('email', 'admin@restaurante.com')->update(['role' => 'admin']);
            DB::table('users')->where('email', 'garcom@restaurante.com')->update(['role' => 'waiter']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
