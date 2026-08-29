<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\MenuCatalog;
use App\Support\UserRole;
use App\Support\WeeklyMenuImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryWeekdayMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_without_days_appears_every_day(): void
    {
        $category = $this->categoryWithProduct('Bebidas', null);

        $this->assertTrue($category->isAvailableOnDay('monday'));
        $this->assertTrue($category->isAvailableOnDay('sunday'));
        $this->assertSame(1, MenuCatalog::categories('tuesday')->count());
    }

    public function test_monday_only_category_hidden_on_other_days(): void
    {
        $monday = $this->categoryWithProduct('Cardápio Segunda', ['monday']);
        $this->categoryWithProduct('Sempre', null);

        $this->assertTrue($monday->isAvailableOnDay('monday'));
        $this->assertFalse($monday->isAvailableOnDay('tuesday'));

        $mondayCatalog = MenuCatalog::categories('monday');
        $this->assertTrue($mondayCatalog->contains(fn (Category $c) => $c->id === $monday->id));

        $tuesdayCatalog = MenuCatalog::categories('tuesday');
        $this->assertFalse($tuesdayCatalog->contains(fn (Category $c) => $c->id === $monday->id));
        $this->assertSame(1, $tuesdayCatalog->count());
    }

    public function test_weekday_categories_appear_before_always_available_ones(): void
    {
        $this->categoryWithProduct('Bebidas', null, 'Suco');
        $monday = $this->categoryWithProduct('Cardápio Segunda', ['monday'], 'Prato segunda');
        $this->categoryWithProduct('Acompanhamentos', null, 'Arroz');

        $catalog = MenuCatalog::categories('monday');

        $this->assertSame(
            ['Cardápio Segunda', 'Acompanhamentos', 'Bebidas'],
            $catalog->pluck('name')->all()
        );
        $this->assertSame($monday->id, $catalog->first()->id);
    }

    public function test_public_menu_lists_weekday_categories_before_drinks(): void
    {
        $today = WeeklyMenuImages::todayKey();

        $this->categoryWithProduct('Bebidas', null, 'Refrigerante');
        $this->categoryWithProduct('Cardápio de hoje', [$today], 'Prato do dia');

        $response = $this->get(route('public.menu'));

        $weekdayPos = strpos($response->getContent(), 'Cardápio de hoje');
        $drinksPos = strpos($response->getContent(), 'Bebidas');

        $this->assertNotFalse($weekdayPos);
        $this->assertNotFalse($drinksPos);
        $this->assertLessThan($drinksPos, $weekdayPos);
    }

    public function test_public_menu_only_shows_today_categories(): void
    {
        $today = WeeklyMenuImages::todayKey();
        $other = $today === 'monday' ? 'tuesday' : 'monday';

        $todayCat = $this->categoryWithProduct('Hoje', [$today], 'Prato de hoje');
        $this->categoryWithProduct('Outro dia', [$other], 'Prato de outro dia');

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('Prato de hoje')
            ->assertDontSee('Prato de outro dia')
            ->assertSee($todayCat->name);
    }

    public function test_admin_can_save_available_days_on_category(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('categories.store'), [
            'name' => 'Segunda especial',
            'is_active' => '1',
            'available_days' => ['monday', 'wednesday'],
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Segunda especial',
        ]);

        $category = Category::query()->where('name', 'Segunda especial')->first();
        $this->assertSame(['monday', 'wednesday'], $category->available_days);
        $this->assertStringContainsString('Segunda', $category->availableDaysLabel());
    }

    public function test_saving_no_days_means_all_days(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::create([
            'name' => 'Geral',
            'is_active' => true,
            'available_days' => ['friday'],
        ]);

        $this->actingAs($admin)->put(route('categories.update', $category), [
            'name' => 'Geral',
            'is_active' => '1',
        ])->assertRedirect(route('categories.index'));

        $this->assertNull($category->fresh()->available_days);
        $this->assertSame('Todos os dias', $category->fresh()->availableDaysLabel());
    }

    private function categoryWithProduct(string $name, ?array $days, string $productName = 'Produto'): Category
    {
        $category = Category::create([
            'name' => $name,
            'is_active' => true,
            'available_days' => $days,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => $productName.' '.$name,
            'price' => 25,
            'is_available' => true,
        ]);

        return $category;
    }
}
