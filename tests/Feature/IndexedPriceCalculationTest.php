<?php

namespace Tests\Feature;

use App\Models\Consumption;
use App\Models\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexedPriceCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 400 Bad Request when required fields are missing.
     */
    public function test_missing_fields_returns_400(): void
    {
        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-01',
            // missing end_date & formula
        ]);

        $response->assertStatus(400)
                 ->assertJsonStructure(['error']);
    }

    /**
     * Test 400 Bad Request when formula does not contain [OMIE_MD].
     */
    public function test_formula_without_omie_tag_returns_400(): void
    {
        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-05',
            'formula' => '(1.2 * 0.6) + 0.88' // missing [OMIE_MD]
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'La fórmula debe contener obligatoriamente la etiqueta [OMIE_MD].']);
    }

    /**
     * Test 400 Bad Request when start_date is after end_date.
     */
    public function test_start_date_after_end_date_returns_400(): void
    {
        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-01',
            'formula' => '([OMIE_MD]*0.6)+0.88'
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'La fecha de inicio (start_date) no puede ser posterior a la fecha de fin (end_date).']);
    }

    /**
     * Test 404 Not Found when date range lacks database records.
     */
    public function test_missing_db_records_returns_404(): void
    {
        // Seed only 1 day
        Consumption::create(['date' => '2025-03-01', 'h1' => 2.0]);
        Price::create(['date' => '2025-03-01', 'h1' => 0.10]);

        // Request 2 days (2025-03-01 to 2025-03-02) -> 2025-03-02 is missing
        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-02',
            'formula' => '([OMIE_MD]*0.6)+0.88'
        ]);

        $response->assertStatus(404)
                 ->assertJsonStructure(['error']);
    }

    /**
     * Test 500 Internal Server Error when formula is syntactically broken.
     */
    public function test_broken_formula_syntax_returns_500(): void
    {
        Consumption::create(['date' => '2025-03-01', 'h1' => 2.0]);
        Price::create(['date' => '2025-03-01', 'h1' => 0.10]);

        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-01',
            'formula' => '([OMIE_MD] * 0.6 + (' // invalid syntax
        ]);

        $response->assertStatus(500)
                 ->assertJsonStructure(['error']);
    }

    /**
     * Test 200 OK returning exact calculated indexed price.
     */
    public function test_successful_indexed_price_calculation_returns_200(): void
    {
        // Day 1: h1 price=0.10, consumption=10.0 => formula ([OMIE_MD]*0.6)+0.88 => (0.10*0.6)+0.88 = 0.94 => importe = 0.94 * 10 = 9.4
        Consumption::create([
            'date' => '2025-03-01',
            'h1' => 10.0,
            'h2' => 20.0,
        ]);

        Price::create([
            'date' => '2025-03-01',
            'h1' => 0.10, // evaluated = 0.94, importe = 9.4
            'h2' => 0.20, // evaluated = (0.20*0.6)+0.88 = 1.00, importe = 20.0
        ]);

        // Total importes = 9.4 + 20.0 = 29.4
        // Total consumos = 10.0 + 20.0 = 30.0
        // Indexed price = 29.4 / 30.0 = 0.98

        $response = $this->postJson('/calculate', [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-01',
            'formula' => '([OMIE_MD]*0.6)+0.88'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'price_indexed' => 0.98
                 ]);
    }
}
