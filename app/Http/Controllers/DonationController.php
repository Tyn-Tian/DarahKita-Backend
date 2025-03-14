<?php

namespace App\Http\Controllers;

use App\Models\BloodStock;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Physical;
use App\Models\User;
use Doctrine\DBAL\Query\QueryException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    public function getDonationsByMonth(Request $request)
    {
        try {
            $startDate = Carbon::now()->subMonths(5)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $user = auth()->user();

            $donations = Donation::select(
                DB::raw("MONTH(donations.updated_at) as month"),
                DB::raw("COUNT(*) as total_donations")
            )
                ->join('pmi_centers', 'donations.pmi_center_id', '=', 'pmi_centers.id')
                ->join('users', 'pmi_centers.user_id', '=', 'users.id')
                ->when($user->role == 'pmi', function ($query) use ($user) {
                    return $query->where('users.city', $user->city);
                })
                ->where('donations.status', 'success')
                ->whereBetween('donations.updated_at', [$startDate, $endDate])
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
            $status = $request->input('status');

            $user = auth()->user();
            $isPmi = $user->role === 'pmi';

            $histories = Donation::with(['pmiCenter.user', 'donor.user'])
                ->when($isPmi, function ($query) use ($user) {
                    $query->where('pmi_center_id', $user->pmiCenter->id);
                })
                ->when(!$isPmi, function ($query) use ($user) {
                    $query->where('donor_id', $user->donor->id);
                })
                ->when($status && $status !== 'semua', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->orderByRaw("FIELD(status, 'pending', 'success', 'failed')")
                ->orderByDesc('updated_at')
                ->with(['donorSchedule' => function ($query) {
                    $query->orderByDesc('date')->orderByDesc('time');
                }])
                ->paginate($perPage, ['*'], 'page', $page);

            $response = collect($histories->items())->map(function ($history) use ($isPmi) {
                return [
                    'id' => $history->id,
                    'date' => $history->donorSchedule->date ?? $history->created_at->format('Y-m-d'),
                    'time' => $history->donorSchedule->time ?? $history->created_at->format('H:i:s'),
                    'location' => $history->donorSchedule->location
                        ?? $history->pmiCenter->user->address
                        ?? 'Lokasi tidak tersedia',
                    'status' => $history->status,
                    'name' => $isPmi
                        ? ($history->donor->user->name ?? '-')
                        : ($history->pmiCenter->user->name ?? '-'),
                    'contact' => $isPmi
                        ? ($history->donor->user->phone ?? '-')
                        : ($history->pmiCenter->user->phone ?? '-'),
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

    public function getHistoryDetail(Request $request, string $id)
    {
        try {
            $user = auth()->user();
            $isPmi = $user->role === 'pmi';

            if ($isPmi) {
                $history = Donation::with(['pmiCenter.user', 'donorSchedule', 'physical', 'donor'])
                    ->where('pmi_center_id', $user->pmiCenter->id)
                    ->findOrFail($id);
            } else {
                $history = Donation::with(['pmiCenter.user', 'donorSchedule', 'physical', 'donor'])
                    ->where('donor_id', $user->donor->id)
                    ->findOrFail($id);
            }

            $response = [
                'id' => $history->id,
                'date' => $history->donorSchedule->date ?? $history->created_at->format('Y-m-d'),
                'time' => $history->donorSchedule->time ?? $history->created_at->format('H:i:s'),
                'location' => $history->donorSchedule->location ?? $history->pmiCenter->user->address ?? '-',
                'status' => $history->status,
                'pmi' => $history->pmiCenter->user->name,
                'contact' => $history->pmiCenter->user->phone,
                'blood' => $history->donor->blood_type,
                'rhesus' => $history->donor->rhesus,
                'systolic' => $history->physical->systolic,
                'diastolic' => $history->physical->diastolic,
                'pulse' => $history->physical->pulse,
                'weight' => $history->physical->weight,
                'temperatur' => $history->physical->temperatur,
                'hemoglobin' => $history->physical->hemoglobin,
            ];

            return response()->json([
                'success' => true,
                'message' => 'History donor darah berhasil diambil',
                'data' => $response
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

    public function postAddDonation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string',
            'blood' => 'required|string',
            'rhesus' => 'required|string',
            'systolic' => 'required|string',
            'diastolic' => 'required|string',
            'pulse' => 'required|string',
            'weight' => 'required|string',
            'temperatur' => 'required|string',
            'hemoglobin' => 'required|string',
            'worthy' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $validatedData = $validator->validated();
            $donorUser = User::where('email', $validatedData['email'])->first();
            $pmiCenterId = auth()->user()->pmiCenter->id;

            if ($donorUser && $donorUser->role === 'pmi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak bisa mendonor menggunakan akun PMI'
                ], 400);
            }

            if (!$donorUser) {
                $donorUser = User::create([
                    'id' => Str::uuid(),
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'role' => 'donor'
                ]);

                $donor = Donor::create([
                    'id' => Str::uuid(),
                    'blood_type' => $validatedData['blood'],
                    'rhesus' => $validatedData['rhesus'],
                    'user_id' => $donorUser->id
                ]);
            }

            $donor = Donor::where('user_id', $donorUser->id)->firstOrFail();

            if ($donor->last_donation && Carbon::parse($donor->last_donation)->diffInMonths(Carbon::parse(now())) <= 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada 4 bulan sejak donasi terakhir'
                ], 400);
            }

            $donor->update([
                'blood_type' => $validatedData['blood'],
                'rhesus' => $validatedData['rhesus']
            ]);

            $physical = Physical::create([
                'id' => Str::uuid(),
                'systolic' => $validatedData['systolic'],
                'diastolic' => $validatedData['diastolic'],
                'pulse' => $validatedData['pulse'],
                'weight' => $validatedData['weight'],
                'temperatur' => $validatedData['temperatur'],
                'hemoglobin' => $validatedData['hemoglobin'],
            ]);

            Donation::create([
                'id' => Str::uuid(),
                'status' => $validatedData['worthy'] ? 'success' : 'failed',
                'donor_id' => $donor->id,
                'pmi_center_id' => $pmiCenterId,
                'physical_id' => $physical->id
            ]);

            if ($validatedData['worthy']) {
                $bloodStock = BloodStock::where('blood_type', $validatedData['blood'])
                    ->where('rhesus', $validatedData['rhesus'])
                    ->where('pmi_center_id', $pmiCenterId)
                    ->firstOrFail();

                $bloodStock->update([
                    'quantity' => $bloodStock->quantity + 1
                ]);

                $donor->update([
                    'last_donation' => now()
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil ditambahkan'
            ], 201);
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

    public function getLastDonation(Request $request)
    {
        try {
            $user = auth()->user();
            $donation = Donation::where('donor_id', $user->donor->id)
                ->orderByDesc('updated_at')
                ->firstOrFail();

            $response = [
                'id' => $donation->id,
                'date' => $donation->donorSchedule->date ?? $donation->created_at->format('Y-m-d'),
                'time' => $donation->donorSchedule->time ?? $donation->created_at->format('H:i:s'),
                'location' => $donation->donorSchedule->location ?? $donation->pmiCenter->user->address ?? '-',
                'status' => $donation->status,
                'pmi' => $donation->pmiCenter->user->name,
                'contact' => $donation->pmiCenter->user->phone,
                'blood' => $donation->donor->blood_type,
                'rhesus' => $donation->donor->rhesus,
                'systolic' => $donation->physical->systolic,
                'diastolic' => $donation->physical->diastolic,
                'pulse' => $donation->physical->pulse,
                'weight' => $donation->physical->weight,
                'temperatur' => $donation->physical->temperatur,
                'hemoglobin' => $donation->physical->hemoglobin,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Donasi terakhir berhasil diambil',
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
}
