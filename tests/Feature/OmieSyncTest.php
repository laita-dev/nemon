<?php

namespace Tests\Feature;

use App\Models\Price;
use App\Services\OmieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmieSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test OmieService fetches and creates price record for given date.
     */
    public function test_omie_service_fetches_and_saves_prices(): void
    {
        $service = new OmieService();
        $result = $service->fetchAndSavePrices('2026-08-10');

        $this->assertDatabaseHas('prices', [
            'date' => '2026-08-10',
        ]);

        $this->assertNotNull($result['record']->h1);
        $this->assertNotNull($result['record']->h24);
    }

    /**
     * Test POST /api/omie/sync endpoint triggers live synchronization and returns 200 OK.
     */
    public function test_omie_sync_api_endpoint(): void
    {
        $response = $this->postJson('/api/omie/sync', [
            'date' => '2026-08-10'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'source',
                     'record',
                     'prices',
                     'consumptions'
                 ]);

        $this->assertDatabaseHas('prices', [
            'date' => '2026-08-10',
        ]);
    }
}
