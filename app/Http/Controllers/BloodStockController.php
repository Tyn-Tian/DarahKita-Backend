<?php

namespace App\Http\Controllers;

use App\Models\BloodStock;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BloodStockController extends Controller
{
    public function getBloodStocks(Request $request)
    {
        try {
            $bloodStocks = BloodStock::select(
                'blood_type',
                'rhesus',
                DB::raw('SUM(quantity) as total_stock')
            )
                ->groupBy('blood_type', 'rhesus')
                ->get()
                ->groupBy('blood_type')
                ->map(function ($items, $key) {
                    return [
                        'blood' => $key,
                        'rhesus +' => $items->where('rhesus', '+')->sum('total_stock'),
                        'rhesus -' => $items->where('rhesus', '-')->sum('total_stock'),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Stok darah berhasil diambil',
                'data' => $bloodStocks
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
