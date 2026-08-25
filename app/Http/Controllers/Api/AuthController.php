<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // role is not mass-assignable — set it explicitly so a self-registering
        // user can never grant themselves admin.
        $user = new User();
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $user->role = UserRole::CUSTOMER;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi akun berhasil',
            'data' => [
                'token' => $token,
                'user' => $user,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password tidak sesuai.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    /**
     * Kick off Google OAuth. Stateless because the SPA has no Laravel session —
     * Google returns to the callback which mints a Sanctum token.
     */
    public function googleRedirect(): RedirectResponse
    {
        // No creds yet → bounce back gracefully instead of a raw Socialite error.
        if (! config('services.google.client_id')) {
            return redirect($this->frontendCallbackUrl(['error' => 'google_not_configured']));
        }

        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Google OAuth callback: find-or-create the user, mint a token, then bounce
     * back to the SPA with the token so it can finish logging in.
     */
    public function googleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect($this->frontendCallbackUrl(['error' => 'google_failed']));
        }

        $email = $googleUser->getEmail();
        if (! $email) {
            return redirect($this->frontendCallbackUrl(['error' => 'no_email']));
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            // New Google user — customer by default, random unusable password.
            $user = new User();
            $user->fill([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna Google',
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->role = UserRole::CUSTOMER;
        }

        // Link/refresh Google metadata (set directly; not mass-assignable).
        $user->google_id = $googleUser->getId();
        $user->avatar = $googleUser->getAvatar();
        $user->save();

        $token = $user->createToken('google_oauth')->plainTextToken;

        return redirect($this->frontendCallbackUrl(['token' => $token]));
    }

    private function frontendCallbackUrl(array $params): string
    {
        return config('services.frontend_url', 'http://localhost:3000').'/auth/callback?'.http_build_query($params);
    }
}
