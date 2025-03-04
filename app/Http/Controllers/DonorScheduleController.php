<?php

namespace App\Http\Controllers;

use App\Models\BloodStock;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorSchedule;
use App\Models\Physical;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DonorScheduleController extends Controller
{
    public function getDonorSchedules(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $city = $request->input('city');
            $pmi = auth()->user()->pmiCenter;

            $donorSchedules = DonorSchedule::with(['pmiCenter.user'])
                ->when($pmi, function ($query) use ($pmi) {
                    $query->where('pmi_center_id', $pmi->id);
                })
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
            $isPmi = $user->pmiCenter;

            if ($isPmi) {
                $response = [
                    'id' => $donorSchedule->id,
                    'date' => $donorSchedule->date,
                    'location' => $donorSchedule->location,
                    'time' => $donorSchedule->time,
                    'name' => $donorSchedule->pmiCenter->user->name,
                    'contact' => $donorSchedule->pmiCenter->user->phone,
                ];
            } else {
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
            }

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

            $physical = Physical::create([
                'id' => Str::uuid()
            ]);

            Donation::create([
                'id' => Str::uuid(),
                'status' => 'pending',
                'donor_id' => $user->donor->id,
                'donor_schedule_id' => $donorSchedule->id,
                'pmi_center_id' => $donorSchedule->pmiCenter->id,
                'physical_id' => $physical->id
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

    public function patchUpdateDonorSchedule(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|string',
            'location' => 'sometimes|string',
            'time' => 'sometimes|string'
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
            $user = auth()->user();
            $donorSchedule = DonorSchedule::where('pmi_center_id', $user->pmiCenter->id)
                ->findOrFail($id);

            $donorSchedule->update([
                'date' => $validatedData['date'],
                'location' => $validatedData['location'],
                'time' => $validatedData['time']
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Jadwal Donor berhasil diupdate'
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

    public function postCreateDonorSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|string',
            'location' => 'required|string',
            'time' => 'required|string'
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
            DonorSchedule::create([
                'id' => Str::uuid(),
                'date' => $validatedData['date'],
                'location' => $validatedData['location'],
                'time' => $validatedData['time'],
                'pmi_center_id' => auth()->user()->pmiCenter->id
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Jadwal donor berhasil dibuat'
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

    public function getDonorScheduleParticipants(Request $request, string $id)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $status = $request->input('status');

            $donorScheduleParticipants = Donation::with(['donor.user'])
                ->where('donor_schedule_id', $id)
                ->when($status && $status !== 'semua', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->orderByRaw("FIELD(status, 'pending', 'success', 'failed')")
                ->where('pmi_center_id', auth()->user()->pmiCenter->id)
                ->paginate($perPage, ['*'], 'page', $page);

            $responses = collect($donorScheduleParticipants->items())->map(function ($participant) {
                return [
                    'id' => $participant->donor->id,
                    'name' => $participant->donor->user->name,
                    'status' => $participant->status,
                    'contact' => $participant->donor->user->phone,
                    'blood' => $participant->donor->blood_type,
                    'rhesus' => $participant->donor->rhesus,
                    'last_donation' => $participant->donor->last_donation ?? '-'
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Peserta donor darah berhasil diambil',
                'data' => $responses,
                'pagination' => [
                    'current_page' => $donorScheduleParticipants->currentPage(),
                    'last_page' => $donorScheduleParticipants->lastPage(),
                    'per_page' => $donorScheduleParticipants->perPage(),
                    'total' => $donorScheduleParticipants->total()
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

    public function getDonorScheduleParticipantDetail(Request $request, string $id, string $donorId)
    {
        try {
            $donorScheduleParticipant = Donation::with(['donor.user', 'physical'])
                ->where('donor_schedule_id', $id)
                ->where('pmi_center_id', auth()->user()->pmiCenter->id)
                ->where('donor_id', $donorId)
                ->firstOrFail();

            $response = [
                'id' => $donorScheduleParticipant->donor->id,
                'name' => $donorScheduleParticipant->donor->user->name,
                'status' => $donorScheduleParticipant->status,
                'contact' => $donorScheduleParticipant->donor->user->phone,
                'blood' => $donorScheduleParticipant->donor->blood_type,
                'rhesus' => $donorScheduleParticipant->donor->rhesus,
                'last_donation' => $donorScheduleParticipant->donor->last_donation ?? '-',
                'systolic' => $donorScheduleParticipant->physical->systolic ?? '',
                'diastolic' => $donorScheduleParticipant->physical->diastolic ?? '',
                'pulse' => $donorScheduleParticipant->physical->pulse ?? '',
                'weight' => $donorScheduleParticipant->physical->weight ?? '',
                'temperatur' => $donorScheduleParticipant->physical->temperatur ?? '',
                'hemoglobin' => $donorScheduleParticipant->physical->hemoglobin ?? ''
            ];

            return response()->json([
                'success' => true,
                'message' => 'Peserta donor darah berhasil diambil',
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

    public function postUpdateStatusParticipant(Request $request, string $id, string $donorId)
    {
        $validator = Validator::make($request->all(), [
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
            $pmiCenterId = auth()->user()->pmiCenter->id;

            $donor = Donor::findOrFail($donorId);
            $donor->update([
                'blood_type' => $validatedData['blood'],
                'rhesus' => $validatedData['rhesus']
            ]);

            $donation = Donation::where('pmi_center_id', $pmiCenterId)
                ->where('donor_id', $donorId)
                ->firstOrFail();

            if ($donation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Update data peserta sudah dilakukan'
                ], 400);
            }

            $donation->update([
                'status' => $validatedData['worthy'] ? 'success' : 'failed'
            ]);

            $physical = Physical::findOrFail($donation->physical_id);
            $physical->update([
                'systolic' => $validatedData['systolic'],
                'diastolic' => $validatedData['diastolic'],
                'pulse' => $validatedData['pulse'],
                'weight' => $validatedData['weight'],
                'temperatur' => $validatedData['temperatur'],
                'hemoglobin' => $validatedData['hemoglobin'],
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
                'message' => 'Update status peserta berhasil',
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

    public function postAddParticipant(Request $request, string $id)
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
                    'message' => 'Ini adalah akun resmi PMI'
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
                'donor_schedule_id' => $id,
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
}
