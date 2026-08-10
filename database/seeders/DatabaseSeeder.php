<?php

namespace Database\Seeders;

use App\Models\Consumption;
use App\Models\Price;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic electricity consumption and OMIE price data.
     */
    public function run(): void
    {
        // Seed recent period from June 1, 2026 to August 9, 2026
        $startDate = Carbon::create(2026, 6, 1);
        $endDate = Carbon::create(2026, 8, 9);

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();

            // Check if consumption already exists for date
            $consumptionData = ['date' => $dateStr];
            $priceData = ['date' => $dateStr];

            for ($h = 1; $h <= 24; $h++) {
                // Realistic hourly profile (higher consumption during morning 08-11 and evening 19-22)
                $isPeakHour = ($h >= 8 && $h <= 11) || ($h >= 19 && $h <= 22);
                $baseConsumption = $isPeakHour ? 2.5 : 1.1;
                $consumptionValue = round($baseConsumption + (sin($h) * 0.4) + (rand(0, 50) / 100), 3);
                $consumptionData["h{$h}"] = max(0.2, $consumptionValue);

                // Realistic OMIE_MD price profile in €/kWh (e.g. €0.05 to €0.18)
                $basePrice = $isPeakHour ? 0.12 : 0.06;
                $priceValue = round($basePrice + (cos($h) * 0.02) + (rand(0, 30) / 1000), 4);
                $priceData["h{$h}"] = max(0.01, $priceValue);
            }

            // Daylight saving or 25th hour (optional / null for standard days)
            $consumptionData['h25'] = null;
            $priceData['h25'] = null;

            // Update or create to avoid duplicate key errors
            Consumption::updateOrCreate(['date' => $dateStr], $consumptionData);
            Price::updateOrCreate(['date' => $dateStr], $priceData);

            $currentDate->addDay();
        }
    }
}
