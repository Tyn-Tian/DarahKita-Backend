<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Doctrine\DBAL\Query\QueryException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    public function getDonationsByMonth(Request $request)
    {
        try {
            $donations = Donation::select(
                DB::raw("MONTH(date) as month"),
                DB::raw("COUNT(*) as total_donations")
            )
                ->groupBy('month')
                ->orderBy('month', 'ASC')
                ->get();

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

            $formattedData = [];

            foreach ($donations as $donation) {
                $formattedData[$months[$donation->month]] = [
                    'donations' => $donation->total_donations
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Data donor berhasil diambil',
                'data' => $formattedData
            ]);
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
