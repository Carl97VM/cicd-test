<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = false;
        $latencyMs = 0;
        $storageOk = false;
        $cacheOk = false;

        try {
            $t0 = microtime(true);
            DB::connection()->getPdo();
            $latencyMs = (int) ((microtime(true) - $t0) * 1000);
            $dbOk = true;

            $storageOk = is_writable(storage_path('framework/views'));

            Cache::put('health_check', true, 5);
            $cacheOk = Cache::has('health_check');
        } catch (\Throwable $e) {
            // Mantener estados en false
        }

        $isHealthy = $dbOk && $storageOk && $cacheOk;

        $versionFile = base_path('RELEASE_ID');
        $versionContent = @file_get_contents($versionFile);
        $version = ($versionContent !== false) ? trim($versionContent) : '1.0.0-dev';

        $context = [
            'release' => $version,
            'env' => config('app.env'),
            'status' => $isHealthy ? 'ok' : 'degraded',
            'user_id' => auth()->id() ?? 'guest',
        ];

        if ($isHealthy) {
            Log::info('Health Check auditado', $context);
        } else {
            Log::warning('Health Check degradado detectado', $context);
        }

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'version' => $version,
            ],
            'db' => [
                'ok' => $dbOk,
                'latency_ms' => $latencyMs,
                'driver' => config('database.default'),
            ],
            'cache' => ['ok' => $cacheOk],
            'storage' => ['ok' => $storageOk],
        ], $isHealthy ? 200 : 503);
    }
}
public function ( $x ){}
