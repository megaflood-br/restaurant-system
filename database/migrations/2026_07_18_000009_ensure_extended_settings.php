<?php

use App\Support\AppSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSettings::ensureDefaults();
    }

    public function down(): void
    {
        //
    }
};
