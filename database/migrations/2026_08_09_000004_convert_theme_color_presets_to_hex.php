<?php

use App\Support\MenuTheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('settings')
            ->where('group', 'digital_menu')
            ->where('key', 'theme_color')
            ->get();

        foreach ($rows as $row) {
            $normalized = MenuTheme::normalize($row->value);

            if ($normalized !== $row->value) {
                DB::table('settings')
                    ->where('id', $row->id)
                    ->update([
                        'value' => $normalized,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible — hex values cannot be mapped back to preset names reliably.
    }
};
