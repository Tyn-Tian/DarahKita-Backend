<?php

namespace App\Http\Controllers;

use App\Models\DonorSchedule;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DonorScheduleController extends Controller
{
    public function getDonorSchedules(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $city = $request->input('city');

            $donorSchedules = DonorSchedule::with(['pmiCenter.user'])
                ->when($city, function ($query) use ($city) {
                    $query->whereHas('pmiCenter.user', function ($query) use ($city) {
                        $query->where('city', $city);
                    });
                })
                ->where(function ($query) {
                    $query->where('date', '>', Carbon::today())
                        ->orWhere(function ($query) {
                            $query->where('date', '=', Carbon::today())
                                ->where('time', '>', Carbon::now('Asia/Jakarta')->format('H:i:s'));
                        });
                })
                ->orderBy('date')
                ->orderBy('time')
                ->paginate($perPage, ['*'], 'page', $page);

            $formatedData = collect($donorSchedules->items())->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'date' => $schedule->date,
                    'location' => $schedule->location,
                    'time' => $schedule->time,
                    'name' => $schedule->pmiCenter->user->name,
                    'contact' => $schedule->pmiCenter->user->phone ?? "-"
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal donor berhasil diambil',
                'data' => $formatedData,
                'pagination' => [
                    'current_page' => $donorSchedules->currentPage(),
                    'last_page' => $donorSchedules->lastPage(),
                    'per_page' => $donorSchedules->perPage(),
                    'total' => $donorSchedules->total()
                ]
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
