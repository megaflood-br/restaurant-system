<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['general', 'cnpj', ''],
            ['general', 'address', ''],
            ['general', 'opening_time', '09:00'],
            ['general', 'closing_time', '22:00'],
            ['general', 'delivery_origin_lat', ''],
            ['general', 'delivery_origin_lng', ''],
            ['general', 'logo_image', null],
            ['whatsapp_agent', 'enabled', false],
            ['whatsapp_agent', 'use_builtin_bot', true],
            ['whatsapp_agent', 'forward_to_n8n', true],
            ['whatsapp_agent', 'restaurant_name', ''],
            ['whatsapp_agent', 'welcome_message', ''],
            ['whatsapp_agent', 'menu_followup_message', ''],
            ['whatsapp_agent', 'extras_message', ''],
            ['whatsapp_agent', 'address_message', ''],
            ['whatsapp_agent', 'payment_message', ''],
            ['whatsapp_agent', 'pix_message', ''],
            ['whatsapp_agent', 'confirmed_message', ''],
            ['whatsapp_agent', 'pix_key', ''],
            ['whatsapp_agent', 'estimated_minutes', 45],
            ['whatsapp_agent', 'menu_image', null],
        ];

        foreach ($defaults as [$group, $key, $value]) {
            $exists = DB::table('settings')
                ->where('group', $group)
                ->where('key', $key)
                ->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'group' => $group,
                    'key' => $key,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
