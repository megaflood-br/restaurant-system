<?php

use App\Support\WeeklyMenuImages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')
            ->where('group', 'whatsapp_agent')
            ->where('key', 'menu_image')
            ->value('value');

        $exists = DB::table('settings')
            ->where('group', 'whatsapp_agent')
            ->where('key', 'menu_images')
            ->exists();

        if (! $exists) {
            $images = filled($legacy)
                ? WeeklyMenuImages::fromLegacy((string) $legacy)
                : WeeklyMenuImages::empty();

            DB::table('settings')->insert([
                'group' => 'whatsapp_agent',
                'key' => 'menu_images',
                'value' => json_encode($images),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'whatsapp_agent')
            ->where('key', 'menu_images')
            ->delete();
    }
};
