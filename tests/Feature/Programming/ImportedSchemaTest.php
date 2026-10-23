<?php

namespace Tests\Feature\Programming;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ImportedSchemaTest extends TestCase
{
    use DatabaseMigrations;

    private const TYPES = '2026_10_05_220523_create_employee_types_table';

    private const EMPLOYEES = '2026_10_05_220605_create_employees_table';

    private const DEFAULT_TYPE = '2026_10_06_060716_add_is_default_to_employee_types_table';

    protected function migrateFreshUsing(): array
    {
        return ['--path' => ['database/migrations/0001_01_01_000000_create_users_table.php']];
    }

    public static function importedTypeVersions(): array
    {
        return ['respaldo antiguo' => [false], 'respaldo actualizado' => [true]];
    }

    private function importedTypes(bool $withDefault): void
    {
        Schema::create('employee_types', function (Blueprint $table) use ($withDefault) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            if ($withDefault) {
                $table->boolean('is_default')->default(false);
            }
            $table->timestamps();
        });

        $type = [
            'id' => 23,
            'name' => 'Conductor importado',
            'description' => 'Descripción conservada del respaldo',
            'created_at' => '2025-08-01 10:00:00',
            'updated_at' => '2026-10-02 11:30:00',
        ];
        if ($withDefault) {
            $type['is_default'] = true;
        }
        DB::table('employee_types')->insert($type);
    }

    #[DataProvider('importedTypeVersions')]
    public function test_pending_migrations_register_imported_tables_without_replacing_records(bool $withDefault): void
    {
        $this->importedTypes($withDefault);
        (require database_path('migrations/'.self::EMPLOYEES.'.php'))->up();
        Schema::table('employees', function (Blueprint $table) {
            $table->string('legacy_reference')->nullable();
        });
        DB::table('employees')->insert([
            'id' => 57, 'dni' => '74320198',
            'first_name' => 'Personal conservado', 'last_name' => 'Del respaldo',
            'birth_date' => '1988-03-15', 'phone' => '999123456',
            'email' => 'personal.importado@example.test', 'status' => true,
            'password' => 'hash-importado-que-no-debe-cambiar',
            'address' => 'Dirección importada de Chiclayo', 'image_path' => 'employees/respaldo.jpg',
            'employee_type_id' => 23, 'legacy_reference' => 'IMPORT-2025-057',
            'created_at' => '2025-08-04 09:00:00', 'updated_at' => '2026-10-03 12:00:00',
        ]);

        $employeeBefore = (array) DB::table('employees')->first();
        $employeeColumnsBefore = Schema::getColumnListing('employees');
        $typeBefore = (array) DB::table('employee_types')->first();
        $paths = array_map(fn ($migration) => 'database/migrations/'.$migration.'.php', [self::TYPES, self::EMPLOYEES, self::DEFAULT_TYPE]);

        $this->artisan('migrate', ['--path' => $paths, '--force' => true])->assertSuccessful();

        $this->assertSame($employeeBefore, (array) DB::table('employees')->first());
        $this->assertSame($employeeColumnsBefore, Schema::getColumnListing('employees'));
        $this->assertSame(array_merge($typeBefore, ['is_default' => $withDefault ? 1 : 0]), (array) DB::table('employee_types')->first());
        foreach ([self::TYPES, self::EMPLOYEES, self::DEFAULT_TYPE] as $migration) {
            $this->assertDatabaseHas('migrations', ['migration' => $migration]);
        }
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('employee_types', 1);

        $this->artisan('migrate', ['--path' => $paths, '--force' => true])->assertSuccessful();
        $this->assertSame($employeeBefore, (array) DB::table('employees')->first());
        $this->assertSame(3, DB::table('migrations')->whereIn('migration', [self::TYPES, self::EMPLOYEES, self::DEFAULT_TYPE])->count());
    }

    public static function incompleteTables(): array
    {
        return [
            'tipos sin marcas de tiempo' => ['employee_types', self::TYPES],
            'personal sin campos requeridos' => ['employees', self::EMPLOYEES],
        ];
    }

    #[DataProvider('incompleteTables')]
    public function test_incomplete_import_is_rejected_without_changing_its_table(string $tableName, string $migration): void
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('import_reference')->nullable();
        });
        DB::table($tableName)->insert(['id' => 91, 'name' => 'Dato del respaldo', 'import_reference' => 'CONSERVAR']);
        $columnsBefore = Schema::getColumnListing($tableName);
        $recordsBefore = DB::table($tableName)->get()->toArray();

        try {
            Artisan::call('migrate', ['--path' => ['database/migrations/'.$migration.'.php'], '--force' => true]);
            $this->fail('La migración debía rechazar la estructura incompleta.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('La tabla '.$tableName.' importada tiene una estructura incompleta.', $exception->getMessage());
        }

        $this->assertSame($columnsBefore, Schema::getColumnListing($tableName));
        $this->assertEquals($recordsBefore, DB::table($tableName)->get()->toArray());
        $this->assertDatabaseMissing('migrations', ['migration' => $migration]);
    }
}
