<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DonorController extends Controller
{
    public function getProfile()
    {
        try {
            $user = auth()->user();
            $donor = Donor::where('user_id', $user->id)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil difetch',
                'data' => [
                    'name' => $user->name ?? "",
                    'email' => $user->email ?? "",
                    'address' => $user->address ?? "",
                    'city' => $user->city ?? "",
                    'phone' => $user->phone ?? "",
                    'blood' => $donor->blood_type ?? "",
                    'rhesus' => $donor->rhesus ?? "",
                    'avatar' => Storage::url($user->avatar) ?? "",
                ]
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

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'blood' => 'sometimes|string|min:1|max:2',
            'rhesus' => 'sometimes|string|min:1|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $validatedData = $validator->validated();
            User::where('id', $user->id)->update([
                'name' => $validatedData['name'],
                'phone' => $validatedData['phone'],
                'city' => $validatedData['city'],
                'address' => $validatedData['address']
            ]);
            Donor::where('user_id', $user->id)->update([
                'blood_type' => $validatedData['blood'],
                'rhesus' => $validatedData['rhesus']
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil diupdate'
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

    public function getTopDonors()
    {
        try {
            $user = auth()->user();

            $topDonors = Donor::with('user')->withCount(['donations' => function ($query) use ($user) {
                $query->where('status', 'success');

                if ($user->role == 'pmi') {
                    $query->whereHas('pmiCenter.user', function ($query) use ($user) {
                        $query->where('city', $user->city);
                    });
                }
            }])
                ->orderByDesc('donations_count')
                ->limit(5)
                ->get()
                ->map(function ($donor) {
                    return [
                        'name' => $donor->user->name ?? "",
                        'donations' => $donor->donations_count
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data pendonor berhasil diambil',
                'data' => $topDonors
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
