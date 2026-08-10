<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('printing', 'auto_print_on_create', false);
        Setting::set('printing', 'print_on_preparing', true);
    }

    public function down(): void
    {
        Setting::set('printing', 'auto_print_on_create', true);
        Setting::set('printing', 'print_on_preparing', false);
    }
};
