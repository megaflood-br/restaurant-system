<?php

namespace Database\Seeders;

use App\Support\AppSettings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        AppSettings::ensureDefaults();
    }
}
