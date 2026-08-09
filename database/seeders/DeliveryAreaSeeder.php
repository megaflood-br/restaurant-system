<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use Illuminate\Database\Seeder;

class DeliveryAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => 'Centro', 'fee' => 5.00, 'sort_order' => 1],
            ['name' => 'Jardins', 'fee' => 8.00, 'sort_order' => 2],
            ['name' => 'Vila Nova', 'fee' => 10.00, 'sort_order' => 3],
            ['name' => 'Zona Sul', 'fee' => 12.00, 'sort_order' => 4],
        ];

        foreach ($areas as $area) {
            DeliveryArea::firstOrCreate(
                ['name' => $area['name']],
                array_merge($area, ['is_active' => true])
            );
        }
    }
}
