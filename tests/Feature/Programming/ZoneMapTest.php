<?php

namespace Tests\Feature\Programming;

use App\Models\Programming\Zone;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZoneMapTest extends TestCase
{
    use DatabaseMigrations;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_19_223236_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_10_15_210133_create_zones_table.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function zone(array $overrides = []): Zone
    {
        return Zone::create(array_merge([
            'name' => 'Moshoqueque',
            'department' => 'Lambayeque',
            'province' => 'Chiclayo',
            'district' => 'Jose Leonardo Ortiz',
            'description' => 'Zona comercial',
            'coordinates' => [
                ['lat' => -6.749, 'lng' => -79.837],
                ['lat' => -6.752, 'lng' => -79.837],
                ['lat' => -6.752, 'lng' => -79.833],
            ],
            'status' => true,
        ], $overrides));
    }

    public function test_map_renders_all_zones_including_inactive_zones_and_location_filters(): void
    {
        $active = $this->zone();
        $inactive = $this->zone(['name' => 'Chiclayo Centro', 'district' => 'Chiclayo', 'status' => false]);

        $this->get(route('admin.programming.zones.map'))
            ->assertOk()
            ->assertViewIs('admin.programming.zones.map')
            ->assertViewHas('mapZones', function ($zones) use ($active, $inactive) {
                return $zones->count() === 2
                    && $zones->firstWhere('id', $active->id)['coordinates'][0] === [-6.749, -79.837]
                    && $zones->firstWhere('id', $inactive->id)['status'] === false;
            })
            ->assertSee('Moshoqueque')
            ->assertSee('Chiclayo Centro')
            ->assertSee('filter-department', false)
            ->assertSee('filter-province', false)
            ->assertSee('filter-district', false);
    }

    public function test_map_handles_empty_zones(): void
    {
        $this->get(route('admin.programming.zones.map'))
            ->assertOk()
            ->assertViewHas('mapZones', fn ($zones) => $zones->isEmpty())
            ->assertSee('Todavía no hay zonas registradas.');
    }

    public function test_invalid_coordinates_are_reported_without_breaking_other_polygons(): void
    {
        $this->zone();
        $invalidZones = [
            $this->zone(['name' => 'Sin perímetro', 'coordinates' => []]),
            $this->zone(['name' => 'Fuera de rango', 'coordinates' => [
                ['lat' => 200, 'lng' => -79], ['lat' => -6, 'lng' => -79], ['lat' => -7, 'lng' => -78],
            ]]),
            $this->zone(['name' => 'Datos incorrectos', 'coordinates' => [
                ['lat' => 'texto', 'lng' => -79], ['lat' => -6, 'lng' => -79], ['lat' => -7, 'lng' => -78],
            ]]),
            $this->zone(['name' => 'Puntos repetidos', 'coordinates' => array_fill(0, 3, ['lat' => -6, 'lng' => -79])]),
        ];

        $this->get(route('admin.programming.zones.map'))
            ->assertOk()
            ->assertViewHas('mapZones', function ($zones) use ($invalidZones) {
                foreach ($invalidZones as $invalid) {
                    $mapZone = $zones->firstWhere('id', $invalid->id);
                    if ($mapZone['has_valid_coordinates'] || $mapZone['coordinates'] !== []) {
                        return false;
                    }
                }

                return $zones->where('has_valid_coordinates', true)->count() === 1;
            })
            ->assertSee('Algunas zonas no tienen un perímetro válido.');
    }

    public function test_zone_text_cannot_inject_markup_or_close_the_map_script(): void
    {
        $this->zone([
            'name' => '</script><script>alert("zona")</script>',
            'description' => '<img src=x onerror=alert("descripcion")>',
        ]);

        $this->get(route('admin.programming.zones.map'))
            ->assertOk()
            ->assertDontSee('</script><script>alert("zona")</script>', false)
            ->assertDontSee('<img src=x onerror=alert("descripcion")>', false)
            ->assertSee('&lt;/script&gt;&lt;script&gt;alert(&quot;zona&quot;)&lt;/script&gt;', false);
    }

    public function test_zone_used_by_a_personnel_group_cannot_be_deleted(): void
    {
        Schema::create('personnel_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
        });
        $zone = $this->zone();
        DB::table('personnel_groups')->insert(['zone_id' => $zone->id]);

        $this->deleteJson(route('admin.programming.zones.destroy', $zone))
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Esta zona pertenece a un grupo de personal. Cambie la zona del grupo o desactive la zona para conservar sus datos.');

        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    public function test_unused_zone_can_be_deleted_before_personnel_groups_are_installed(): void
    {
        $zone = $this->zone();

        $this->deleteJson(route('admin.programming.zones.destroy', $zone))->assertOk();

        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }
}
