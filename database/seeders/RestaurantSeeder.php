<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@restaurante.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'garcom@restaurante.com'],
            [
                'name' => 'Garçom',
                'password' => Hash::make('password'),
                'role' => 'waiter',
                'email_verified_at' => now(),
            ]
        );

        User::where('email', 'admin@restaurante.com')->update(['role' => 'admin']);
        User::where('email', 'garcom@restaurante.com')->update(['role' => 'waiter']);

        $admin = User::where('email', 'admin@restaurante.com')->first();

        $deliveryAreas = [
            ['name' => 'Centro', 'fee' => 5.00, 'sort_order' => 1],
            ['name' => 'Jardins', 'fee' => 8.00, 'sort_order' => 2],
            ['name' => 'Vila Nova', 'fee' => 10.00, 'sort_order' => 3],
            ['name' => 'Zona Sul', 'fee' => 12.00, 'sort_order' => 4],
        ];

        foreach ($deliveryAreas as $area) {
            DeliveryArea::firstOrCreate(
                ['name' => $area['name']],
                array_merge($area, ['is_active' => true])
            );
        }

        if (Category::count() === 0) {
            $entradas = Category::create([
                'name' => 'Entradas',
                'description' => 'Porções para começar a refeição',
            ]);

            $principais = Category::create([
                'name' => 'Pratos principais',
                'description' => 'Pratos completos do cardápio',
            ]);

            $bebidas = Category::create([
                'name' => 'Bebidas',
                'description' => 'Refrigerantes, sucos e água',
            ]);

            $bruschetta = Product::create([
                'category_id' => $entradas->id,
                'name' => 'Bruschetta',
                'description' => 'Pão italiano com tomate, manjericão e azeite',
                'price' => 24.90,
            ]);

            $file = Product::create([
                'category_id' => $principais->id,
                'name' => 'Filé ao molho madeira',
                'description' => 'Filé mignon com batatas rústicas',
                'price' => 59.90,
            ]);

            $risoto = Product::create([
                'category_id' => $principais->id,
                'name' => 'Risoto de camarão',
                'description' => 'Arborio com camarões e limão siciliano',
                'price' => 54.90,
            ]);

            Product::create([
                'category_id' => $bebidas->id,
                'name' => 'Suco natural',
                'description' => 'Laranja, abacaxi ou maracujá',
                'price' => 12.90,
            ]);
        } else {
            $file = Product::where('name', 'Filé ao molho madeira')->first();
            $risoto = Product::where('name', 'Risoto de camarão')->first();
            $bruschetta = Product::where('name', 'Bruschetta')->first();
        }

        $stockCategoryData = [
            ['name' => 'Alimentos', 'sort_order' => 1],
            ['name' => 'Bebidas', 'sort_order' => 2],
            ['name' => 'Limpeza', 'sort_order' => 3],
            ['name' => 'Gás', 'sort_order' => 4],
            ['name' => 'Embalagens', 'sort_order' => 5],
        ];

        $stockCategories = [];
        foreach ($stockCategoryData as $data) {
            $stockCategories[$data['name']] = StockCategory::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['is_active' => true])
            );
        }

        $ingredients = [
            ['name' => 'Carne bovina', 'unit' => 'kg', 'package_size' => 5, 'cost_price' => 225.00, 'current_stock' => 8, 'minimum_stock' => 5, 'category' => 'Alimentos'],
            ['name' => 'Camarão', 'unit' => 'kg', 'package_size' => 2, 'cost_price' => 120.00, 'current_stock' => 2, 'minimum_stock' => 3, 'category' => 'Alimentos'],
            ['name' => 'Arroz arbóreo', 'unit' => 'kg', 'package_size' => 5, 'cost_price' => 45.00, 'current_stock' => 10, 'minimum_stock' => 4, 'category' => 'Alimentos'],
            ['name' => 'Pão italiano', 'unit' => 'un', 'package_size' => 20, 'cost_price' => 36.00, 'current_stock' => 30, 'minimum_stock' => 10, 'category' => 'Alimentos'],
            ['name' => 'Detergente neutro', 'unit' => 'L', 'package_size' => 5, 'cost_price' => 32.00, 'current_stock' => 5, 'minimum_stock' => 2, 'category' => 'Limpeza'],
            ['name' => 'Papel higiênico', 'unit' => 'un', 'package_size' => 12, 'cost_price' => 28.80, 'current_stock' => 24, 'minimum_stock' => 12, 'category' => 'Limpeza'],
            ['name' => 'Gás P13', 'unit' => 'un', 'package_size' => 1, 'cost_price' => 110.00, 'current_stock' => 3, 'minimum_stock' => 1, 'category' => 'Gás'],
            ['name' => 'Embalagem delivery', 'unit' => 'un', 'package_size' => 100, 'cost_price' => 85.00, 'current_stock' => 100, 'minimum_stock' => 30, 'category' => 'Embalagens'],
        ];

        $ingredientModels = [];
        foreach ($ingredients as $data) {
            $categoryName = $data['category'];
            unset($data['category']);

            $ingredientModels[$data['name']] = Ingredient::updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['stock_category_id' => $stockCategories[$categoryName]->id])
            );
        }

        if ($file && $risoto && $bruschetta) {
            $fileRecipe = Recipe::updateOrCreate(
                ['product_id' => $file->id],
                [
                    'name' => 'Ficha — Filé ao molho madeira',
                    'yield_portions' => 1,
                    'preparation_method' => "1. Temperar o filé com sal e pimenta.\n2. Grelhar por 4 min de cada lado.\n3. Finalizar com molho madeira.",
                    'is_active' => true,
                ]
            );
            $fileRecipe->ingredients()->sync([
                $ingredientModels['Carne bovina']->id => ['quantity' => 0.25],
            ]);
            $file->update(['recipe_id' => $fileRecipe->id]);

            $risotoRecipe = Recipe::updateOrCreate(
                ['product_id' => $risoto->id],
                [
                    'name' => 'Ficha — Risoto de camarão',
                    'yield_portions' => 1,
                    'preparation_method' => "1. Refogar arroz com cebola.\n2. Adicionar caldo aos poucos.\n3. Finalizar com camarões e limão.",
                    'is_active' => true,
                ]
            );
            $risotoRecipe->ingredients()->sync([
                $ingredientModels['Arroz arbóreo']->id => ['quantity' => 0.15],
                $ingredientModels['Camarão']->id => ['quantity' => 0.12],
            ]);
            $risoto->update(['recipe_id' => $risotoRecipe->id]);

            $bruschettaRecipe = Recipe::updateOrCreate(
                ['product_id' => $bruschetta->id],
                [
                    'name' => 'Ficha — Bruschetta',
                    'yield_portions' => 1,
                    'preparation_method' => "1. Tostar fatias de pão.\n2. Cobrir com tomate, manjericão e azeite.",
                    'is_active' => true,
                ]
            );
            $bruschettaRecipe->ingredients()->sync([
                $ingredientModels['Pão italiano']->id => ['quantity' => 2],
            ]);
            $bruschetta->update(['recipe_id' => $bruschettaRecipe->id]);
        }

        $maria = Customer::firstOrCreate(
            ['email' => 'maria.silva@email.com'],
            [
                'name' => 'Maria Silva',
                'phone' => '(11) 98765-4321',
                'birth_date' => '1985-03-15',
                'address' => 'Rua das Flores, 123',
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01310-100',
                'notes' => 'Cliente frequente, prefere delivery.',
            ]
        );

        Customer::firstOrCreate(
            ['email' => 'joao.santos@email.com'],
            [
                'name' => 'João Santos',
                'phone' => '(11) 91234-5678',
                'city' => 'São Paulo',
                'state' => 'SP',
            ]
        );

        if ($maria->interactions()->doesntExist()) {
            $maria->interactions()->create([
                'type' => 'note',
                'content' => 'Cliente elogiou o risoto de camarão na última visita.',
                'user_id' => $admin->id,
            ]);

            $maria->interactions()->create([
                'type' => 'call',
                'content' => 'Ligou para confirmar pedido de aniversário.',
                'user_id' => $admin->id,
            ]);
        }
    }
}
