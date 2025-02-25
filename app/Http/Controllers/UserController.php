<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Google_Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function googleLogin(Request $request)
    {
        $token = $request->input('token');

        $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($token);

        if ($payload) {
            $email = $payload['email'];
            $googleAvatarUrl = $payload['picture'];
            $user = User::where('email', $email)->first();

            if (!$user->avatar) {
                $avatarContents = file_get_contents($googleAvatarUrl);
                $filename = 'avatar-' . time() . '-' . uniqid() . '.jpg';
                $path = 'avatars/' . $filename;

                Storage::disk('public')->put($path, $avatarContents);

                $user->update([
                    'avatar' => $path
                ]);
            }

            if ($user) {
                $jwt = JWTAuth::fromUser($user);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified',
                    'token' => $jwt,
                    'user' => $user
                ], 200);
            } else {
                return response()->json(['success' => false, 'error' => 'Email not found']);
            }
        } else {
            return response()->json(['success' => false, 'error' => 'Invalid token']);
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'id' => Str::orderedUuid(),
                'name' => $request->name,
                'email' => $request->email,
                'role' => 'donor',
                'password' => Hash::make($request->password),
            ]);

            Donor::create([
                'id' => Str::orderedUuid(),
                'user_id' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registrasi Berhasil',
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $user = User::where('email', $credentials['email'])->firstOrFail();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email dan password tidak cocok.',
                ], 401);
            }

            $jwt = JWTAuth::fromUser($user);
            return response()->json([
                'success' => true,
                'message' => 'Email verified',
                'token' => $jwt,
                'user' => $user
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Model tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
