<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    use RefreshDatabase;

    private function createCountry(string $code, string $name): Country
    {
        return Country::create(['code' => $code, 'name' => $name]);
    }

    public function test_admin_can_list_zones(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Zone::create(['code' => 'EU', 'name' => 'European Union']);
        Zone::create(['code' => 'ASEAN', 'name' => 'ASEAN']);

        $response = $this->getJson('/api/v1/system/zones');

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_create_zone_with_countries(): void
    {
        $this->actingAsSuperAdmin();
        $de = $this->createCountry('DE', 'Germany');
        $fr = $this->createCountry('FR', 'France');

        $response = $this->postJson('/api/v1/system/zones', [
            'code' => 'EU',
            'name' => 'European Union',
            'description' => '欧盟成员国',
            'country_ids' => [$de->id, $fr->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'EU')
            ->assertJsonPath('data.countries.0.code', 'DE')
            ->assertJsonPath('data.countries.1.code', 'FR');

        $this->assertDatabaseHas('zones', ['code' => 'EU']);
        $this->assertDatabaseHas('zone_countries', ['country_id' => $de->id]);
        $this->assertDatabaseHas('zone_countries', ['country_id' => $fr->id]);
    }

    public function test_zone_code_must_be_uppercase_format(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/zones', [
            'code' => 'invalid-code',
            'name' => 'Invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_zone_code_must_be_unique(): void
    {
        Zone::create(['code' => 'EU', 'name' => 'EU']);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/zones', [
            'code' => 'EU',
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_create_zone(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/zones', [
            'code' => 'ASEAN',
            'name' => 'ASEAN',
        ]);

        $response->assertStatus(403);
    }

    public function test_country_can_belong_to_multiple_zones(): void
    {
        // 新加坡同时在 ASEAN 和 APAC
        $sg = $this->createCountry('SG', 'Singapore');
        $asean = Zone::create(['code' => 'ASEAN', 'name' => 'ASEAN']);
        $apac = Zone::create(['code' => 'APAC', 'name' => 'Asia-Pacific']);
        $asean->countries()->sync([$sg->id]);
        $apac->countries()->sync([$sg->id]);

        $this->assertCount(2, $sg->fresh()->zones);
    }

    public function test_super_admin_can_update_zone_countries(): void
    {
        $this->actingAsSuperAdmin();
        $de = $this->createCountry('DE', 'Germany');
        $fr = $this->createCountry('FR', 'France');
        $it = $this->createCountry('IT', 'Italy');

        $zone = Zone::create(['code' => 'EU', 'name' => 'EU']);
        $zone->countries()->sync([$de->id, $fr->id]);

        $response = $this->putJson("/api/v1/system/zones/{$zone->id}", [
            'country_ids' => [$de->id, $it->id], // 把 fr 换成 it
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('zone_countries', ['zone_id' => $zone->id, 'country_id' => $de->id]);
        $this->assertDatabaseHas('zone_countries', ['zone_id' => $zone->id, 'country_id' => $it->id]);
        $this->assertDatabaseMissing('zone_countries', ['zone_id' => $zone->id, 'country_id' => $fr->id]);
    }

    public function test_zone_country_pair_must_be_unique(): void
    {
        $de = $this->createCountry('DE', 'Germany');
        $zone = Zone::create(['code' => 'EU', 'name' => 'EU']);
        $zone->countries()->attach($de->id);

        $this->expectException(QueryException::class);
        $zone->countries()->attach($de->id);
    }

    public function test_super_admin_can_delete_zone_and_detach_countries(): void
    {
        $this->actingAsSuperAdmin();
        $de = $this->createCountry('DE', 'Germany');
        $zone = Zone::create(['code' => 'TO_DELETE', 'name' => 'Delete Me']);
        $zone->countries()->attach($de->id);

        $response = $this->deleteJson("/api/v1/system/zones/{$zone->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
        $this->assertDatabaseMissing('zone_countries', ['zone_id' => $zone->id]);
        // country 本身不应被删
        $this->assertDatabaseHas('countries', ['id' => $de->id]);
    }

    public function test_unauthenticated_cannot_access_zones(): void
    {
        $this->getJson('/api/v1/system/zones')->assertStatus(401);
    }

    public function test_filter_zones_by_keywords(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        Zone::create(['code' => 'EU', 'name' => 'European Union']);
        Zone::create(['code' => 'NA', 'name' => 'North America']);
        Zone::create(['code' => 'ASEAN', 'name' => 'ASEAN']);

        $response = $this->getJson('/api/v1/system/zones?keywords=European');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('EU', $response->json('data.list.0.code'));
    }
}
