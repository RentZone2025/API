<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
// user model
use App\Models\User;
use App\Notifications\VerifyEmailNotification;

use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;
use PragmaRX\Google2FA\Exceptions\Google2FAException;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Str;
use DB;
use Carbon\Carbon;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'fullname' => ['required', 'string', 'max:255'],
                "username" => ["required", "string", "max:255", 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::create([
                'fullname' => $validatedData['fullname'],
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
            ]);

            //$token = $user->createToken('auth_token')->plainTextToken;

            $user->sendEmailVerificationNotification();

            return response()->json([
                'status' => 'success',
                'message' => 'Felhasználó sikeresen regisztrálva.',
                'user' => $user,
                //'access_token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Érvénytelen adatok.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user_with_trashed = User::withTrashed()->where('email', $request->input('email'))->first();
            if ($user_with_trashed && $user_with_trashed->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A fiókja fel lett függesztve. Kérjük, vegye fel a kapcsolatot az ügyfélszolgálattal.'
                ], 401);
            }

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A megadott felhasználónév vagy jelszó helytelen. Kérjük, próbálja újra.'
                ], 401);
            }

            $user = Auth::user();

            if (!auth()->user()->hasVerifiedEmail()) {
                return response()->json([
                    'status'=> 'error',
                    'message'=> 'Email not verified! Please check your mailbox!'
                    ],500);
            }

            if (!$user->two_factor_secret) {
                return $this->completeLogin($user);
            }

            return response()->json([
                'status' => '2fa_required',
                'message' => 'You need 2FA authentication!',
                'user_id' => $user->id,
                '2fa' => true,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Érvénytelen adatok.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jelszó visszaállítási link elküldve az email címére.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Nem sikerült elküldeni a jelszó visszaállítási linket. Kérjük, próbálja újra később.',
            ], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'id' => 'required', // Az encodedUserId
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
        ]);

        // Dekódoljuk a user ID-t
        $userId = base64_decode($request->id);

        $email = DB::table('users')->where('id', $userId)->value('email');

        // Keressük meg a token-hez és user ID-hez tartozó bejegyzést
        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        //return response()->json(['message' => $tokenData->created_at], 400);

        if (!Hash::check($request->token, $tokenData->token)) {
            return response()->json(['message' => 'Érvénytelen token.'], 400);
        }

        // Ellenőrizzük a token érvényességét
        if (Carbon::parse($tokenData->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'A token lejárt.'], 400);
        }

        // Keressük meg a felhasználót az ID alapján
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'Felhasználó nem található.'], 404);
        }

        // Állítsuk vissza a jelszót
        $user->password = Hash::make($request->password);
        $user->save();

        // Töröljük a felhasznált tokent
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        event(new PasswordReset($user));

        return response()->json(['message' => 'Jelszó sikeresen visszaállítva.'], 200);
    }

    public function verifyEmail(Request $request)
    {
        $user = User::find($request->route('id'));

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully'], 200);
    }


    public function verify2FA(Request $request)
    {
        $request->validate([
            'two_factor_code' => ['required', 'string'],
            'user_id' => ['required', 'integer'],
        ]);

        $userId = $request->user_id ?? null;

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid session. Please login again.'
            ], 401);
        }

        $user = User::find($userId);
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->two_factor_code
        );

        if ($valid) {
            session()->forget('2fa_user_id');
            Auth::attempt(['email' => $user->email, 'password' => $user->password]);
            return $this->completeLogin($user);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code'
            ], 401);
        }
    }

    private function completeLogin($user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            "user" => $user,
            'access_token' => $token
        ]);
    }

    public function setup2FA()
    {
        try {
            $user = Auth::user();

            if ($user->two_factor_secret) {
                return response()->json([
                    'status' => 'already_enabled',
                    'message' => '2FA is already enabled for this user',
                ], 400);
            }

            $google2fa = new Google2FA(new Bacon());
            $secretKey = $google2fa->generateSecretKey();

            $qrCode = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secretKey
            );

            return response()->json([
                'status' => 'success',
                'message' => '',
                'secret_key' => $secretKey,
                'qr_code' => $qrCode
            ], 200);

        } catch (Google2FAException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error',
            ], 0);
        }
    }

    public function verifySetup2FA(Request $request)
    {
        $request->validate([
            'two_factor_code' => ['required', 'string'],
            'two_factor_secret' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $google2fa = new Google2FA();
        $secretKey = $request->two_factor_secret ?? null;

        if (!$secretKey) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA setup not initiated',
            ], 400);
        }

        if (!$google2fa->verifyKey($secretKey, $request->two_factor_code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code',
            ], 401);
        }

        $user->two_factor_secret = $secretKey;
        $user->save();

        // Clear the temporary secret from the session
        session()->forget('temp_2fa_secret');

        return response()->json([
            'status' => 'success',
            'message' => '2FA setup verified and enabled',
        ], 200);
    }


    public function deactivate2FA()
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