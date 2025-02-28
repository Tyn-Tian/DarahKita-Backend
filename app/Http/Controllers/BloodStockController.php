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
            $user = auth()->user();

            $bloodStocks = BloodStock::select(
                'blood_stocks.blood_type',
                'blood_stocks.rhesus',
                DB::raw('SUM(blood_stocks.quantity) as total_stock')
            )
                ->join('pmi_centers', 'blood_stocks.pmi_center_id', '=', 'pmi_centers.id')
                ->join('users', 'pmi_centers.user_id', '=', 'users.id')
                ->when($user->role == 'pmi', function ($query) use ($user) {
                    return $query->where('users.city', $user->city); 
                })
                ->groupBy('blood_stocks.blood_type', 'blood_stocks.rhesus')
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
