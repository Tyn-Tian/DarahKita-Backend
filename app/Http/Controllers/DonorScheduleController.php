<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\DonorSchedule;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function getDonorScheduleDetail(Request $request, string $id)
    {
        try {
            $user = auth()->user();
            $donorSchedule = DonorSchedule::with(['pmiCenter.user'])
                ->findOrFail($id);
            $isDonor = false;
            $lastDonation = $user->donor->last_donation;
            $isScheduleRegistered = Donation::where('donor_id', $user->donor->id)
                ->where('status', 'pending')
                ->where('donor_schedule_id', $id)
                ->exists();
            $isRegistered = Donation::where('donor_id', $user->donor->id)
                ->where('status', 'pending')
                ->exists();

            if (
                !$lastDonation ||
                Carbon::parse($lastDonation)->diffInMonths($donorSchedule->date) >= 4 &&
                !$isScheduleRegistered &&
                !$isRegistered
            ) {
                $isDonor = true;
            }

            $response = [
                'id' => $donorSchedule->id,
                'date' => $donorSchedule->date,
                'location' => $donorSchedule->location,
                'time' => $donorSchedule->time,
                'name' => $donorSchedule->pmiCenter->user->name,
                'contact' => $donorSchedule->pmiCenter->user->phone,
                'lastDonation' => $lastDonation,
                'isDonor' => $isDonor,
                'isScheduleRegistered' => $isScheduleRegistered,
                'isRegistered' => $isRegistered
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detail jadwal donor berhasil diambil',
                'data' => $response
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

    public function postRegisterDonorSchedule(Request $request, string $id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            $isScheduleRegistered = Donation::where('donor_id', $user->donor->id)
                ->where('status', 'pending')
                ->where('donor_schedule_id', $id)
                ->exists();
            $isRegistered = Donation::where('donor_id', $user->donor->id)
                ->where('status', 'pending')
                ->exists();

            if ($isScheduleRegistered || $isRegistered) {
                return response()->json([
                    'status' => false,
                    'message' => 'User sudah melakukan registrasi donor darah'
                ], 400);
            }

            $donorSchedule = DonorSchedule::findOrFail($id);

            Donation::create([
                'id' => Str::uuid(),
                'date' => $donorSchedule->date,
                'time' => $donorSchedule->time,
                'status' => 'pending',
                'donor_id' => $user->donor->id,
                'donor_schedule_id' => $donorSchedule->id,
                'pmi_center_id' => $donorSchedule->pmiCenter->id,
            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Daftar jadwal donor darah berhasil',
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
