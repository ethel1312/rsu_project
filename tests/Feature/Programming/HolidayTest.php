<?php

namespace Tests\Feature\Programming;

use App\Models\Programming\Holiday;
use App\Models\User;
use App\Services\Programming\HolidayImportService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use DatabaseMigrations;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_19_223236_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_10_23_100000_create_holidays_table.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now('America/Lima')->setDateTime(2026, 10, 23, 10, 0, 0));
        Http::preventStrayRequests();
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['date' => '2026-11-01', 'description' => 'Día de Todos los Santos'], $overrides);
    }

    private function apiHoliday(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-11-01',
            'name' => "All Saints' Day",
            'countryCode' => 'PE',
            'nationalHoliday' => true,
            'holidayTypes' => ['Public'],
            'subdivisionCodes' => null,
        ], $overrides);
    }

    public function test_holiday_routes_require_authentication(): void
    {
        $holiday = Holiday::create($this->data());

        $this->get(route('admin.programming.holidays.index'))->assertRedirect(route('login'));
        $this->getJson(route('admin.programming.holidays.create'))->assertUnauthorized();
        $this->getJson(route('admin.programming.holidays.edit', $holiday))->assertUnauthorized();
        $this->postJson(route('admin.programming.holidays.store'), $this->data())->assertUnauthorized();
        $this->putJson(route('admin.programming.holidays.update', $holiday), $this->data())->assertUnauthorized();
        $this->patchJson(route('admin.programming.holidays.toggle-status', $holiday), ['is_active' => false])->assertUnauthorized();
        $this->deleteJson(route('admin.programming.holidays.destroy', $holiday))->assertUnauthorized();
        $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_crud_defaults_to_active_and_allows_explicit_status_changes(): void
    {
        $this->admin();
        $this->postJson(route('admin.programming.holidays.store'), $this->data())->assertOk();
        $holiday = Holiday::firstOrFail();
        $this->assertTrue($holiday->is_active);
        $this->assertSame('2026-11-01', $holiday->date->toDateString());

        $this->putJson(route('admin.programming.holidays.update', $holiday), $this->data([
            'description' => 'Feriado revisado', 'is_active' => false,
        ]))->assertOk();
        $this->assertFalse($holiday->fresh()->is_active);
        $this->assertSame('Feriado revisado', $holiday->fresh()->description);
        $this->assertSame(0, Holiday::active()->count());

        $this->patchJson(route('admin.programming.holidays.toggle-status', $holiday), ['is_active' => true])->assertOk();
        $this->assertTrue($holiday->fresh()->is_active);
        $this->patchJson(route('admin.programming.holidays.toggle-status', $holiday), ['is_active' => true])->assertOk();
        $this->assertTrue($holiday->fresh()->is_active);

        $this->deleteJson(route('admin.programming.holidays.destroy', $holiday))->assertOk();
        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_manual_registration_rejects_duplicate_dates_invalid_dates_and_empty_descriptions(): void
    {
        $this->admin();
        $holiday = Holiday::create($this->data());
        $this->postJson(route('admin.programming.holidays.store'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson(route('admin.programming.holidays.store'), $this->data(['date' => '2026-02-30', 'description' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'description']);
        $this->postJson(route('admin.programming.holidays.store'), $this->data(['date' => '2026-12-25', 'is_active' => 'incorrecto']))
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');

        // Editing the same date is allowed, while colliding with another date is rejected.
        $this->putJson(route('admin.programming.holidays.update', $holiday), $this->data())->assertOk();
        Holiday::create($this->data(['date' => '2026-12-25']));
        $this->putJson(route('admin.programming.holidays.update', $holiday), $this->data(['date' => '2026-12-25']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('holidays', 2);
    }

    public function test_list_and_forms_render_and_filters_show_weekday_and_global_counters(): void
    {
        $this->admin();
        Holiday::create($this->data(['date' => '2026-01-01']));
        $holiday = Holiday::create($this->data());
        Holiday::create($this->data(['date' => '2026-12-25', 'is_active' => false]));

        $this->get(route('admin.programming.holidays.index'))->assertOk()->assertSee('Lista de Feriados')->assertSee('Cargar feriados de Perú');
        $this->get(route('admin.programming.holidays.create'))->assertOk()->assertSee('Fecha del feriado');
        $this->get(route('admin.programming.holidays.edit', $holiday))->assertOk()->assertSee('2026-11-01');

        $this->getJson(route('admin.programming.holidays.index', [
            'date_from' => '2026-10-01', 'date_to' => '2026-12-31', 'status' => '1',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.date', '01/11/2026')
            ->assertJsonPath('data.0.day_name', 'Domingo')
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.active', 2)
            ->assertJsonPath('stats.upcoming', 1)
            ->assertJsonPath('stats.year', 2026);

        $this->getJson(route('admin.programming.holidays.index', ['status' => '0']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.is_active', false);
        $this->getJson(route('admin.programming.holidays.index', ['date_from' => '2026-12-25', 'date_to' => '2026-11-01']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_import_is_repeatable_preserves_existing_manual_descriptions_and_inactive_status(): void
    {
        $this->admin();
        Holiday::create($this->data(['description' => 'Feriado desactivado manualmente', 'is_active' => false]));
        Http::fake(['*' => Http::response([
            $this->apiHoliday(),
            $this->apiHoliday(['date' => '2026-12-25', 'name' => 'Christmas Day']),
            $this->apiHoliday(['date' => '2026-12-25', 'name' => 'Christmas Day']),
        ])]);

        $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])->assertOk()->assertJsonPath('created', 2)->assertJsonPath('skipped', 1);
        $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])->assertOk()->assertJsonPath('created', 0)->assertJsonPath('skipped', 3);
        $this->assertDatabaseCount('holidays', 3);
        $this->assertDatabaseHas('holidays', ['date' => '2026-11-01', 'description' => 'Feriado desactivado manualmente', 'is_active' => false]);
        $this->assertDatabaseHas('holidays', ['date' => '2026-12-25', 'description' => 'Navidad', 'is_active' => true]);
        Http::assertSent(fn (Request $request) => $request->url() === config('holidays.api_url').'/PE/2026' && $request->method() === 'GET');
    }

    public function test_list_preserves_description_text_for_the_client_text_renderer(): void
    {
        $this->admin();
        $description = 'Feriado municipal & jornada <especial>';
        Holiday::create($this->data(['description' => $description]));

        $this->getJson(route('admin.programming.holidays.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('data.0.description', $description);
    }

    public function test_import_prefers_local_name_and_excludes_regional_or_non_public_holidays(): void
    {
        $this->admin();
        Http::fake(['*' => Http::response([
            $this->apiHoliday(['localName' => 'Todos los Santos']),
            $this->apiHoliday(['date' => '2026-11-02', 'nationalHoliday' => false]),
            $this->apiHoliday(['date' => '2026-11-03', 'holidayTypes' => ['Observance']]),
        ])]);

        $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])->assertOk()->assertJsonPath('created', 2);
        $this->assertDatabaseCount('holidays', 2);
        $this->assertDatabaseHas('holidays', ['description' => 'Todos los Santos']);
    }

    public function test_real_provider_response_is_reconciled_with_the_peruvian_national_calendar(): void
    {
        $this->admin();
        // Captured from GET https://nagerholidays.com/api/v4/Holidays/PE/2026.
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/peru-holidays-2026.json')), true, 512, JSON_THROW_ON_ERROR);
        Http::fake(['*' => Http::response($payload)]);

        $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])
            ->assertOk()->assertJsonPath('created', 16)->assertJsonPath('skipped', 0);

        // Ley 31788 and the 2026 calendar of PROMPERÚ list June 7, not Easter Sunday.
        $this->assertSame([
            '2026-01-01', '2026-04-02', '2026-04-03', '2026-05-01', '2026-06-07', '2026-06-29',
            '2026-07-23', '2026-07-28', '2026-07-29', '2026-08-06', '2026-08-30', '2026-10-08',
            '2026-11-01', '2026-12-08', '2026-12-09', '2026-12-25',
        ], Holiday::orderBy('date')->get()->map(fn ($holiday) => $holiday->date->toDateString())->all());
        $this->assertDatabaseMissing('holidays', ['date' => '2026-04-05']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-06-07', 'description' => 'Batalla de Arica y Día de la Bandera']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-07-23', 'description' => 'Día de la Fuerza Aérea del Perú']);
    }

    public function test_invalid_payload_rows_never_leave_a_partial_import(): void
    {
        $this->admin();
        foreach ([
            $this->apiHoliday(['date' => '2027-12-25']),
            $this->apiHoliday(['date' => '2026-02-30']),
            $this->apiHoliday(['countryCode' => 'CL']),
            $this->apiHoliday(['name' => '   ']),
            ['date' => '2026-12-25'],
        ] as $invalidRow) {
            Http::fake(['*' => Http::response([$this->apiHoliday(), $invalidRow])]);
            $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])
                ->assertStatus(502)->assertJsonPath('message', 'El servicio devolvió datos de feriados incompletos o inválidos. No se guardó ningún cambio. Intente nuevamente o regístrelos manualmente.');
            $this->assertDatabaseCount('holidays', 0);
        }
    }

    public function test_import_reports_unavailable_empty_invalid_json_and_timeout_responses(): void
    {
        $this->admin();
        foreach ([Http::response('', 503), Http::response([], 200), Http::response('not-json', 200), Http::failedConnection()] as $response) {
            Http::fake(['*' => $response]);
            $this->postJson(route('admin.programming.holidays.import'), ['year' => 2026])
                ->assertStatus(502)->assertJsonStructure(['message']);
            $this->assertDatabaseCount('holidays', 0);
        }
    }

    public function test_import_rolls_back_when_persisting_a_later_holiday_fails(): void
    {
        Http::fake(['*' => Http::response([
            $this->apiHoliday(),
            $this->apiHoliday(['date' => '2026-12-25', 'name' => 'Christmas Day']),
        ])]);
        Holiday::creating(function (Holiday $holiday) {
            if ($holiday->date->toDateString() === '2026-12-25') {
                throw new RuntimeException('Simulated storage failure');
            }
        });

        try {
            app(HolidayImportService::class)->import(2026);
            $this->fail('Expected storage failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        } finally {
            Holiday::flushEventListeners();
        }

        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_invalid_import_year_is_rejected_before_calling_the_api(): void
    {
        $this->admin();
        foreach ([null, 'incorrecto', 2025, 2032] as $year) {
            $this->postJson(route('admin.programming.holidays.import'), ['year' => $year])->assertUnprocessable()->assertJsonValidationErrors('year');
        }
        Http::assertNothingSent();
    }
}
