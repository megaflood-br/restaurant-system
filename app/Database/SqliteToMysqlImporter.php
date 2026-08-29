<?php

namespace App\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SqliteToMysqlImporter
{
    /** @var list<string> */
    private const TABLE_ORDER = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'settings',
        'categories',
        'stock_categories',
        'delivery_areas',
        'customers',
        'products',
        'category_product',
        'ingredients',
        'product_ingredient',
        'product_variants',
        'recipes',
        'recipe_ingredient',
        'inventory_movements',
        'orders',
        'order_items',
        'customer_interactions',
        'whatsapp_messages',
        'cash_movements',
        'print_jobs',
        'motoboy_settlements',
    ];

    /** @var list<string> */
    private const SKIP_TABLES = [
        'migrations',
        'sqlite_sequence',
    ];

    public function __construct(
        private readonly string $sourceConnection = 'sqlite_legacy',
        private readonly string $targetConnection = 'mysql',
    ) {}

    /**
     * @return array{tables: list<array{name: string, source: int, imported: int, skipped: bool}>, mismatches: list<string>}
     */
    public function dryRun(): array
    {
        return $this->run(import: false, fresh: false);
    }

    /**
     * @return array{tables: list<array{name: string, source: int, imported: int, skipped: bool}>, mismatches: list<string>}
     */
    public function import(bool $fresh = false): array
    {
        return $this->run(import: true, fresh: $fresh);
    }

    /** @return list<string> */
    public function sourceTables(): array
    {
        return $this->orderedTables($this->source());
    }

    /**
     * @return array{tables: list<array{name: string, source: int, imported: int, skipped: bool}>, mismatches: list<string>}
     */
    private function run(bool $import, bool $fresh): array
    {
        $source = $this->source();
        $target = $this->target();
        $tables = $this->orderedTables($source);
        $report = [];
        $mismatches = [];

        if ($import) {
            $target->transaction(function () use ($source, $target, $tables, $fresh, &$report, &$mismatches): void {
                $this->disableForeignKeyChecks($target);

                if ($fresh) {
                    $this->truncateTargetTables($target, $tables);
                }

                foreach ($tables as $table) {
                    $result = $this->copyTable($source, $target, $table, import: true);
                    $report[] = $result;

                    if ($result['source'] !== $result['imported']) {
                        $mismatches[] = "{$table}: origem {$result['source']}, importado {$result['imported']}";
                    }
                }

                $this->resetAutoIncrements($target, $tables);
                $this->enableForeignKeyChecks($target);
            });
        } else {
            foreach ($tables as $table) {
                $report[] = $this->copyTable($source, $target, $table, import: false);
            }
        }

        return [
            'tables' => $report,
            'mismatches' => $mismatches,
        ];
    }

    /** @return array{name: string, source: int, imported: int, skipped: bool} */
    private function copyTable(Connection $source, Connection $target, string $table, bool $import): array
    {
        if (! Schema::connection($this->sourceConnection)->hasTable($table)) {
            return ['name' => $table, 'source' => 0, 'imported' => 0, 'skipped' => true];
        }

        if (! Schema::connection($this->targetConnection)->hasTable($table)) {
            return ['name' => $table, 'source' => 0, 'imported' => 0, 'skipped' => true];
        }

        $sourceCount = (int) $source->table($table)->count();

        if (! $import) {
            return ['name' => $table, 'source' => $sourceCount, 'imported' => 0, 'skipped' => false];
        }

        if ($sourceCount === 0) {
            return ['name' => $table, 'source' => 0, 'imported' => 0, 'skipped' => false];
        }

        $columns = $this->sharedColumns($table);

        if ($columns === []) {
            return ['name' => $table, 'source' => $sourceCount, 'imported' => 0, 'skipped' => true];
        }

        $imported = 0;
        $query = $source->table($table);

        if (in_array('id', $columns, true)) {
            $query->orderBy('id');
        } elseif (in_array('category_id', $columns, true) && in_array('product_id', $columns, true)) {
            $query->orderBy('category_id')->orderBy('product_id');
        }

        $query->chunk(500, function (Collection $rows) use ($target, $table, $columns, &$imported): void {
            $payload = $rows
                ->map(fn ($row) => $this->normalizeRow((array) $row, $columns))
                ->values()
                ->all();

            if ($payload === []) {
                return;
            }

            $target->table($table)->insert($payload);
            $imported += count($payload);
        });

        return ['name' => $table, 'source' => $sourceCount, 'imported' => $imported, 'skipped' => false];
    }

    /** @param  list<string>  $columns */
    /** @param  array<string, mixed>  $row */
    /** @return array<string, mixed> */
    private function normalizeRow(array $row, array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;

            if ($value === '') {
                $normalized[$column] = null;

                continue;
            }

            $normalized[$column] = $value;
        }

        return $normalized;
    }

    /** @return list<string> */
    private function sharedColumns(string $table): array
    {
        $sourceColumns = Schema::connection($this->sourceConnection)->getColumnListing($table);
        $targetColumns = Schema::connection($this->targetConnection)->getColumnListing($table);

        return array_values(array_intersect($sourceColumns, $targetColumns));
    }

    /** @return list<string> */
    private function orderedTables(Connection $source): array
    {
        $tables = collect($source->select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->reject(fn (string $name) => in_array($name, self::SKIP_TABLES, true))
            ->values();

        $priority = array_flip(self::TABLE_ORDER);

        return $tables
            ->sortBy(fn (string $name) => [$priority[$name] ?? 999, $name])
            ->values()
            ->all();
    }

    /** @param  list<string>  $tables */
    private function truncateTargetTables(Connection $target, array $tables): void
    {
        foreach (array_reverse($tables) as $table) {
            if (! Schema::connection($this->targetConnection)->hasTable($table)) {
                continue;
            }

            $target->table($table)->delete();
        }
    }

    /** @param  list<string>  $tables */
    private function resetAutoIncrements(Connection $target, array $tables): void
    {
        if (! in_array($target->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::connection($this->targetConnection)->hasTable($table)) {
                continue;
            }

            if (! in_array('id', Schema::connection($this->targetConnection)->getColumnListing($table), true)) {
                continue;
            }

            $max = $target->table($table)->max('id');
            $next = max(1, ((int) $max) + 1);
            $target->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
        }
    }

    private function disableForeignKeyChecks(Connection $connection): void
    {
        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = OFF'),
            default => null,
        };
    }

    private function enableForeignKeyChecks(Connection $connection): void
    {
        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS=1'),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = ON'),
            default => null,
        };
    }

    private function source(): Connection
    {
        return $this->connection($this->sourceConnection);
    }

    private function target(): Connection
    {
        return $this->connection($this->targetConnection);
    }

    private function connection(string $name): Connection
    {
        $connection = DB::connection($name);

        if (! $connection instanceof Connection) {
            throw new RuntimeException("Conexão inválida: {$name}");
        }

        return $connection;
    }
}
