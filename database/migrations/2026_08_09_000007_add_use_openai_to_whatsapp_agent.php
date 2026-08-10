<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'whatsapp_agent', 'key' => 'use_openai'],
            ['value' => '0', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'whatsapp_agent')
            ->where('key', 'use_openai')
            ->delete();
    }
};
