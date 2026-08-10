<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['whatsapp_agent', 'human_pause_minutes', '60'],
            ['whatsapp_agent', 'human_handoff_message', 'Certo! Vou chamar um atendente humano para continuar com você. Aguarde um momentinho. 🙋'],
            ['whatsapp_agent', 'bot_resumed_message', 'Voltei! Pode continuar seu pedido comigo. Digite *oi* ou *cardápio*.'],
        ];

        foreach ($defaults as [$group, $key, $value]) {
            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'whatsapp_agent')
            ->whereIn('key', ['human_pause_minutes', 'human_handoff_message', 'bot_resumed_message'])
            ->delete();
    }
};
