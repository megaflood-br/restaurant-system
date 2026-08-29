<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\MenuCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMultiCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_multiple_categories_to_product(): void
    {
        $admin = User::factory()->admin()->create();
        $bebidas = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $promocoes = Category::create(['name' => 'Promoções', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'category_ids' => [$bebidas->id, $promocoes->id],
            'name' => 'Suco do dia',
            'price' => 12,
            'is_available' => '1',
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::query()->where('name', 'Suco do dia')->first();
        $this->assertNotNull($product);
        $this->assertSame($bebidas->id, $product->category_id);
        $this->assertEqualsCanonicalizing(
            [$bebidas->id, $promocoes->id],
            $product->categories()->pluck('categories.id')->all()
        );
    }

    public function test_product_appears_in_all_assigned_menu_categories(): void
    {
        $bebidas = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $promocoes = Category::create(['name' => 'Promoções', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $bebidas->id,
            'name' => 'Refrigerante',
            'price' => 8,
            'is_available' => true,
        ]);
        $product->categories()->sync([$bebidas->id, $promocoes->id]);

        $menuCategories = MenuCatalog::categories();

        $this->assertTrue(
            $menuCategories->firstWhere('id', $bebidas->id)?->products->contains('id', $product->id) ?? false
        );
        $this->assertTrue(
            $menuCategories->firstWhere('id', $promocoes->id)?->products->contains('id', $product->id) ?? false
        );
    }

    public function test_product_form_shows_category_checkboxes(): void
    {
        $admin = User::factory()->admin()->create();
        Category::create(['name' => 'Pratos', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Categorias', false)
            ->assertSee('name="category_ids[]"', false);
    }

    public function test_category_ids_validation_requires_at_least_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('products.store'), [
                'category_ids' => [],
                'name' => 'Sem categoria',
                'price' => 10,
            ])
            ->assertSessionHasErrors('category_ids');
    }
}
