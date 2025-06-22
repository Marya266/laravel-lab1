<?php

namespace App\Http\Controllers;

use App\Data\AuthData;
use App\Data\RegisterData;
use App\Data\UserData;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends Controller
{
    private $maxTokens;
    private $tokenExpiration;
    private $refreshTokenExpiration;

    public function __construct()
    {
        $this->maxTokens = env('MAX_TOKENS', 5); // Default to 5 if not set
        $this->tokenExpiration = env('TOKEN_EXPIRATION', 60); // Default to 60 minutes
        $this->refreshTokenExpiration = env('REFRESH_TOKEN_EXPIRATION', 1440); // Default to 24 hours
    }

    public function register(RegisterRequest $request)
    {
        $registerData = $request->toDto();

        try {
            $user = User::create([
                'name' => $registerData->name,
                'email' => $registerData->email,
                'password' => Hash::make($registerData->password),
            ]);

            $userData = UserData::from([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

            return response()->json($userData, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        $loginData = $request->toDto();

        if (!Auth::attempt(['email' => $loginData->email, 'password' => $loginData->password])) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $user = Auth::user();

        // Revoke excess tokens
        $tokensCount = $user->tokens()->count();
        if ($tokensCount >= $this->maxTokens) {
            $user->tokens()->orderBy('created_at', 'asc')->take($tokensCount - $this->maxTokens + 1)->delete();
        }

        $accessToken = $this->generateToken($user);
        $refreshToken = md5(uniqid());

        $authData = AuthData::from([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ]);

        $cookie = cookie('refresh_token', $refreshToken, $this->refreshTokenExpiration, null, null, false, true);

        return response()->json($authData->toArray(), 200)->withCookie($cookie);
    }

    public function user(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        try {
            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            $user = User::find($decoded->user_id);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = $request->user();
        $userData = UserData::from([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return response()->json($userData);
    }

    public function logout(Request $request)
    {
        Cookie::queue(Cookie::forget('refresh_token'));

        return response()->json(['message' => 'Logged out'], 200);
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token not provided'], 400);
        }

        $user = $this->validateRefreshToken($refreshToken);

        if (!$user) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $accessToken = $this->generateToken($user);
        $newRefreshToken = md5(uniqid());

        $authData = AuthData::from([
            'accessToken' => $accessToken,
            'refreshToken' => $newRefreshToken,
        ]);

        $cookie = cookie('refresh_token', $newRefreshToken, $this->refreshTokenExpiration, null, null, false, true);

        return response()->json($authData->toArray(), 200)->withCookie($cookie);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Пароль успешно изменен'], 200);
    }

    protected function generateToken(User $user): string
    {
        $payload = [
            'iss' => config('app.url'),
            'aud' => config('app.url'),
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + (60 * 60), // 1 hour
            'user_id' => $user->id,
        ];
        $jwt = JWT::encode($payload, config('jwt.secret'), 'HS256');

        return $jwt;
    }

    protected function validateRefreshToken(string $token): ?User
    {
        // Add validation logic here
        // For example, check if the token exists in a database or cache

        // This is a placeholder; replace it with your actual validation
        return User::where('id', 1)->first();
    }
}