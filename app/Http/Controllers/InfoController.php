<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InfoController extends Controller
{
    public function serverInfo(): JsonResponse
    {
        return response()->json([
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
        ]);
    }

    public function clientInfo(Request $request): JsonResponse
    {
        return response()->json([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function databaseInfo(): JsonResponse
    {
        return response()->json([
            'database' => config('database.default'),
            'connection' => config('database.connections.'.config('database.default')),
        ]);
    }
}