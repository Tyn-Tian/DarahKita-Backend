<?php

namespace App\Http\Controllers;

use App\Models\DonorSchedule;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DonorScheduleController extends Controller
{
    public function getDonorSchedules()
    {
        try {
            $donorSchedules = DonorSchedule::with(['pmiCenter', 'pmiCenter.user'])
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'date' => $schedule->date,
                        'location' => $schedule->location,
                        'time' => $schedule->time,
                        'name' => $schedule->pmiCenter->user->name,
                        'contact' => $schedule->pmiCenter->user->phone ?? ""
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal donor berhasil diambil',
                'data' => $donorSchedules
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
