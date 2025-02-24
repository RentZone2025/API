<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
// user model
use App\Models\User;

use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;
use PragmaRX\Google2FA\Exceptions\Google2FAException;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'fullname' => ['required', 'string', 'max:255'],
                "username" => ["required", "string", "max:255", 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'phone_number' => ['required', 'string', 'max:20', "unique:users"],
                'birthdate' => ['required', 'string'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::create([
                'fullname' => $validatedData['fullname'],
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'phone_number' => $validatedData['phone_number'],
                'birthdate' => $validatedData['birthdate'],
                'password' => Hash::make($validatedData['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Felhasználó sikeresen regisztrálva.',
                'user' => $user,
                'access_token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Érvénytelen adatok.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function auth($user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'status' => 'success',
            'user' => $user,
            'access_token' => $token,
        ], 200);
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A megadott felhasználónév vagy jelszó helytelen. Kérjük, próbálja újra.'
                ], 401);
            }

            $user = Auth::user();

            if ($user->two_factor_secret == null) {
                return $this->auth($user);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'You need 2FA authentication!',
                    '2fa' => true,
                ], 200);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Érvénytelen adatok.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function verify_2FA(Request $request)
    {

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        Auth::attempt($credentials);

        $user = Auth::user();
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->two_factor_code
        );

        if ($valid) {
            return $this->auth($user);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code'
            ], 401);
        }
    }

    public function activate_2FA()
    {
        try {
            $user = Auth::user();
            $google2fa = new Google2FA(new Bacon());
            $secretKey = $google2fa->generateSecretKey();

            $user->two_factor_secret = $secretKey;
            $user->save();

            $qr_image = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secretKey
            );

            return response()->json([
                'status' => 'success',
                'message' => '2FA has been activated',
                'secret_key' => $secretKey,
                'qr_image' => $qr_image
            ], 200);

        } catch (Google2FAException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error',
            ], 0);
        }
    }

    public function deactivate_2FA()
    {
        $user = Auth::user();
        $user->two_factor_secret = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Two Factor Authentacation successfull deactivated!',
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Sikeresen kijelentkezett.',
        ], 200);
    }
}
