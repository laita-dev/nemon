<?php

namespace App\Http\Controllers;

use App\Models\Consumption;
use App\Models\Price;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NXP\MathExecutor;
use Throwable;

class IndexedPriceController extends Controller
{
    /**
     * Render the main Inertia dashboard view.
     */
    public function index(): Response
    {
        $consumptions = Consumption::orderBy('date', 'desc')->get();
        $prices = Price::orderBy('date', 'desc')->get();

        return Inertia::render('Dashboard', [
            'consumptions' => $consumptions,
            'prices' => $prices,
        ]);
    }

    /**
     * Calculate indexed energy price based on given date range and formula.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculate(Request $request): JsonResponse
    {
        // 1. Validate required fields (400 Bad Request if missing)
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $formula = $request->input('formula');

        if (empty($startDate) || empty($endDate) || empty($formula)) {
            return response()->json([
                'error' => 'Los campos start_date, end_date y formula son obligatorios.'
            ], 400);
        }

        // Validate formula must contain [OMIE_MD] tag
        if (strpos($formula, '[OMIE_MD]') === false) {
            return response()->json([
                'error' => 'La fórmula debe contener obligatoriamente la etiqueta [OMIE_MD].'
            ], 400);
        }

        // Validate valid date format (Y-m-d) and start_date <= end_date
        try {
            $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate)->startOfDay();
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'El formato de fecha es inválido. Debe ser YYYY-MM-DD.'
            ], 400);
        }

        if ($start->gt($end)) {
            return response()->json([
                'error' => 'La fecha de inicio (start_date) no puede ser posterior a la fecha de fin (end_date).'
            ], 400);
        }

        // Clean formula string (trim whitespace or surrounding $ if present)
        $cleanFormula = trim($formula);
        if (str_starts_with($cleanFormula, '$') && str_ends_with($cleanFormula, '$')) {
            $cleanFormula = trim($cleanFormula, '$');
        }

        // 2. Continuous database record check (404 Not Found if missing any day in range)
        try {
            $period = CarbonPeriod::create($start, $end);
            $requiredDates = [];
            foreach ($period as $date) {
                $requiredDates[] = $date->toDateString();
            }

            $consumptionRecords = Consumption::whereIn('date', $requiredDates)->get()->keyBy(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            });

            $priceRecords = Price::whereIn('date', $requiredDates)->get()->keyBy(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            });

            foreach ($requiredDates as $dateStr) {
                if (!$consumptionRecords->has($dateStr) || !$priceRecords->has($dateStr)) {
                    return response()->json([
                        'error' => "No existen registros continuos de consumos y/o precios para la fecha {$dateStr} en la base de datos."
                    ], 404);
                }
            }

            // 3. Perform Indexed Price Calculation
            $executor = new MathExecutor();
            $sumaImportes = 0.0;
            $sumaConsumos = 0.0;

            foreach ($requiredDates as $dateStr) {
                $consumption = $consumptionRecords->get($dateStr);
                $price = $priceRecords->get($dateStr);

                for ($h = 1; $h <= 25; $h++) {
                    $col = "h{$h}";
                    $precioHora = $price->$col;
                    $consumoHora = $consumption->$col;

                    // Skip hours without data
                    if ($precioHora === null || $consumoHora === null) {
                        continue;
                    }

                    $precioHora = (float) $precioHora;
                    $consumoHora = (float) $consumoHora;

                    // Replace [OMIE_MD] with numerical price value
                    $evaluatedFormulaString = str_replace('[OMIE_MD]', (string) $precioHora, $cleanFormula);

                    // Execute math formula
                    $precioEvaluadoHora = (float) $executor->execute($evaluatedFormulaString);

                    $importeHora = $precioEvaluadoHora * $consumoHora;

                    $sumaImportes += $importeHora;
                    $sumaConsumos += $consumoHora;
                }
            }

            if ($sumaConsumos <= 0) {
                return response()->json([
                    'error' => 'La suma total de consumos para el período especificado es 0 o inválida.'
                ], 500);
            }

            $precioIndexado = $sumaImportes / $sumaConsumos;

            return response()->json([
                'price_indexed' => $precioIndexado,
                'summary' => [
                    'suma_importes' => round($sumaImportes, 6),
                    'suma_consumos' => round($sumaConsumos, 6),
                    'total_dias' => count($requiredDates),
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Error al procesar la fórmula matemática o los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consumptions list API
     */
    public function getConsumptions(): JsonResponse
    {
        return response()->json(Consumption::orderBy('date', 'desc')->get());
    }

    /**
     * Get prices list API
     */
    public function getPrices(): JsonResponse
    {
        return response()->json(Price::orderBy('date', 'desc')->get());
    }

    /**
     * Synchronize real-time OMIE prices from external sources (ESIOS / OMIE).
     */
    public function syncOmie(Request $request, \App\Services\OmieService $omieService): JsonResponse
    {
        $dateStr = $request->input('date', Carbon::today()->toDateString());

        try {
            $result = $omieService->fetchAndSavePrices($dateStr);

            return response()->json([
                'message' => "Precios de OMIE para la fecha {$dateStr} sincronizados correctamente.",
                'source' => $result['source'],
                'record' => $result['record'],
                'prices' => Price::orderBy('date', 'desc')->get(),
                'consumptions' => Consumption::orderBy('date', 'desc')->get(),
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Error al sincronizar datos de OMIE: ' . $e->getMessage()
            ], 500);
        }
    }
}
