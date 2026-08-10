<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('group', 'digital_menu')
            ->where('key', 'theme_color')
            ->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'group' => 'digital_menu',
                'key' => 'theme_color',
                'value' => 'orange',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'digital_menu')
            ->where('key', 'theme_color')
            ->delete();
    }
};
