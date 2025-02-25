<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Doctrine\DBAL\Query\QueryException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function getDonationsByMonth(Request $request)
    {
        try {
            $startDate = Carbon::now()->subMonths(5)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $donations = Donation::select(
                DB::raw("MONTH(date) as month"),
                DB::raw("COUNT(*) as total_donations")
            )
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('month')
                ->orderBy('month', 'ASC')
                ->pluck('total_donations', 'month');

            $months = [
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember'
            ];

            $formattedData = collect(range(5, 0))->map(function ($i) use ($donations, $months) {
                $monthNumber = Carbon::now()->subMonths($i)->month;
                return [
                    'month' => $months[$monthNumber],
                    'donations' => $donations[$monthNumber] ?? 0
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Data donor berhasil diambil',
                'data' => $formattedData
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

    public function getHistories(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);

            $user = auth()->user();

            $histories = Donation::with(['pmiCenter.user'])
                ->where('donor_id', $user->donor->id)
                ->orderByRaw("FIELD(status, 'pending', 'success', 'failed')")
                ->orderBy('date', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $response = collect($histories->items())->map(function ($history) {
                return [
                    'id' => $history->id,
                    'date' => $history->date,
                    'status' => $history->status,
                    'pmi' => $history->pmiCenter->user->name
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'History donor darah berhasil diambil',
                'data' => $response,
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total()
                ]
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
