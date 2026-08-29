<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_photo_on_product_without_recipe(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Bebidas', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'category_ids' => [$category->id],
            'name' => 'Coca-Cola Lata',
            'price' => 6.5,
            'is_available' => '1',
            'image' => UploadedFile::fake()->image('coca.jpg', 400, 400),
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::query()->where('name', 'Coca-Cola Lata')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image);
        $this->assertNull($product->recipe_id);
        Storage::disk('public')->assertExists($product->image);
        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString($product->image, $product->image_url);
    }

    public function test_product_image_takes_priority_over_recipe_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $recipe = Recipe::create([
            'name' => 'Ficha',
            'image' => 'recipes/recipe-photo.jpg',
            'yield_portions' => 1,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('recipes/recipe-photo.jpg', 'fake');

        $product = Product::create([
            'category_id' => $category->id,
            'recipe_id' => $recipe->id,
            'name' => 'Prato',
            'price' => 30,
            'is_available' => true,
            'image' => null,
        ]);
        $product->load('recipe');

        $this->assertStringContainsString('recipes/recipe-photo.jpg', (string) $product->image_url);

        $this->actingAs($admin)->put(route('products.update', $product), [
            'category_ids' => [$category->id],
            'recipe_id' => $recipe->id,
            'name' => 'Prato',
            'price' => 30,
            'is_available' => '1',
            'image' => UploadedFile::fake()->image('produto.png', 200, 200),
        ])->assertRedirect(route('products.index'));

        $product->refresh()->load('recipe');
        $this->assertNotNull($product->image);
        $this->assertStringContainsString($product->image, (string) $product->image_url);
        $this->assertStringNotContainsString('recipe-photo.jpg', (string) $product->image_url);
    }

    public function test_product_form_shows_photo_upload_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Foto do produto', false)
            ->assertSee('name="image"', false);
    }
}
