<?php

namespace App\Console\Commands;

use App\Database\SqliteToMysqlImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportDatabaseFromSqliteCommand extends Command
{
    protected $signature = 'db:import-from-sqlite
                            {--sqlite= : Caminho absoluto ou relativo ao arquivo SQLite de origem}
                            {--fresh : Limpa as tabelas do MySQL antes de importar (exceto migrations)}
                            {--dry-run : Mostra contagens sem gravar no MySQL}
                            {--force : Não pedir confirmação}';

    protected $description = 'Copia dados do SQLite legado para o banco MySQL configurado no .env';

    public function handle(): int
    {
        $target = (string) config('database.default');
        $targetDriver = (string) config("database.connections.{$target}.driver");

        if (! in_array($targetDriver, ['mysql', 'mariadb'], true)) {
            $this->error('DB_CONNECTION precisa ser mysql (ou mariadb) no .env antes de importar.');
            $this->line('Configure DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD, depois rode: php artisan migrate --force');

            return self::FAILURE;
        }

        $sqlitePath = $this->resolveSqlitePath();

        if ($sqlitePath === null) {
            return self::FAILURE;
        }

        Config::set('database.connections.sqlite_legacy.database', $sqlitePath);
        DB::purge('sqlite_legacy');

        if (! is_file($sqlitePath)) {
            $this->error("Arquivo SQLite não encontrado: {$sqlitePath}");

            return self::FAILURE;
        }

        if (! Schema::connection($target)->hasTable('orders')) {
            $this->error('O MySQL ainda não tem as tabelas. Rode antes: php artisan migrate --force');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! (bool) $this->option('fresh')) {
            $existingOrders = (int) DB::connection($target)->table('orders')->count();

            if ($existingOrders > 0 && ! $this->option('force')) {
                $this->error("O MySQL já tem {$existingOrders} pedido(s). Use --fresh para limpar antes ou --force para continuar mesmo assim.");

                return self::FAILURE;
            }
        }

        $importer = new SqliteToMysqlImporter(
            sourceConnection: 'sqlite_legacy',
            targetConnection: $target,
        );

        $this->info('Origem SQLite: '.$sqlitePath);
        $this->info('Destino: '.$target.' ('.$targetDriver.')');

        if ($this->option('dry-run')) {
            $this->warn('Modo dry-run — nenhum dado será gravado.');
            $this->printReport($importer->dryRun());

            return self::SUCCESS;
        }

        if ((bool) $this->option('fresh')) {
            $this->warn('Modo --fresh: todas as tabelas de dados no MySQL serão limpas antes da importação.');
        }

        if (! $this->option('force') && ! $this->confirm('Importar dados do SQLite para o MySQL agora?', true)) {
            $this->comment('Importação cancelada.');

            return self::SUCCESS;
        }

        try {
            $result = $importer->import(fresh: (bool) $this->option('fresh'));
        } catch (\Throwable $exception) {
            $this->error('Falha na importação: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->printReport($result);

        if ($result['mismatches'] !== []) {
            $this->warn('Algumas tabelas tiveram diferença entre origem e importado. Revise acima.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Importação concluída.');
        $this->line('Próximos passos:');
        $this->line('  php artisan config:clear');
        $this->line('  php artisan cache:clear');
        $this->line('  Guarde uma cópia do SQLite antigo antes de remover.');

        return self::SUCCESS;
    }

    /** @param  array{tables: list<array{name: string, source: int, imported: int, skipped: bool}>, mismatches: list<string>}  $result */
    private function printReport(array $result): void
    {
        $rows = collect($result['tables'])
            ->map(fn (array $row) => [
                $row['name'],
                $row['skipped'] ? '—' : (string) $row['source'],
                $row['skipped'] ? '—' : (string) $row['imported'],
                $row['skipped'] ? 'ignorada' : 'ok',
            ])
            ->all();

        $this->newLine();
        $this->table(['Tabela', 'Origem', 'Importado', 'Status'], $rows);

        if ($result['mismatches'] !== []) {
            $this->newLine();
            $this->warn('Diferenças:');
            foreach ($result['mismatches'] as $line) {
                $this->line('  - '.$line);
            }
        }
    }

    private function resolveSqlitePath(): ?string
    {
        $option = $this->option('sqlite');

        if (is_string($option) && trim($option) !== '') {
            $path = $option;

            if (! str_starts_with($path, '/')) {
                $path = base_path($path);
            }

            return $path;
        }

        $envPath = env('SQLITE_LEGACY_PATH');

        if (is_string($envPath) && trim($envPath) !== '') {
            if (! str_starts_with($envPath, '/')) {
                return base_path($envPath);
            }

            return $envPath;
        }

        $default = database_path('database.sqlite');

        if (is_file($default)) {
            return $default;
        }

        $this->error('Informe o arquivo SQLite com --sqlite=/caminho/database.sqlite ou SQLITE_LEGACY_PATH no .env');

        return null;
    }
}
