<?php

namespace Tests\Feature;

use App\Database\SqliteToMysqlImporter;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportDatabaseFromSqliteCommandTest extends TestCase
{
    public function test_command_requires_mysql_as_default_connection(): void
    {
        Config::set('database.default', 'sqlite');

        $exit = Artisan::call('db:import-from-sqlite', ['--dry-run' => true]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('DB_CONNECTION precisa ser mysql', Artisan::output());
    }

    public function test_importer_copies_data_preserving_ids_between_sqlite_files(): void
    {
        $sourcePath = database_path('testing-import-source.sqlite');
        $targetPath = database_path('testing-import-target.sqlite');

        foreach ([$sourcePath, $targetPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        Config::set('database.connections.sqlite_legacy', [
            'driver' => 'sqlite',
            'database' => $sourcePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => $targetPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.default', 'mysql');

        DB::purge('sqlite_legacy');
        DB::purge('mysql');

        $this->artisan('migrate', ['--database' => 'sqlite_legacy', '--force' => true]);
        $this->artisan('migrate', ['--database' => 'mysql', '--force' => true]);

        Config::set('database.default', 'sqlite_legacy');
        DB::purge('sqlite_legacy');

        $admin = User::factory()->admin()->create(['email' => 'import-test@example.com']);
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim',
            'price' => 30,
            'is_available' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999000999',
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'PED-IMPORT-0001',
            'customer_id' => $customer->id,
            'type' => 'delivery',
            'status' => 'pending',
            'total' => 30,
            'delivery_fee' => 4,
            'discount' => 4,
            'user_id' => $admin->id,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 30,
            'subtotal' => 30,
            'product_name' => 'Cupim',
        ]);

        $sourceOrderId = $order->id;
        $sourceCustomerId = $customer->id;

        $importer = new SqliteToMysqlImporter('sqlite_legacy', 'mysql');
        $result = $importer->import(fresh: true);

        $this->assertSame([], $result['mismatches']);

        Config::set('database.default', 'mysql');
        DB::purge('mysql');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame($sourceOrderId, Order::query()->value('id'));
        $this->assertSame($sourceCustomerId, Customer::query()->value('id'));
        $this->assertEquals(4.0, (float) Order::query()->value('discount'));
    }

    protected function tearDown(): void
    {
        foreach ([
            database_path('testing-import-source.sqlite'),
            database_path('testing-import-target.sqlite'),
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }
}
