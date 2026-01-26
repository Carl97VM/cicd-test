<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // 1. Verificación de Base de Datos (PostgreSQL)
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $results['db'] = [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $dbStart) * 1000, 2),
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $results['db'] = ['status' => 'fail', 'error' => 'Database connection refused'];
        }

        // 2. Verificación de Almacenamiento (Permisos de Escritura)
        try {
            $storagePath = storage_path('app/health_check.txt');
            file_put_contents($storagePath, 'check');
            unlink($storagePath);
            $results['storage'] = ['status' => 'ok', 'writable' => true];
        } catch (\Exception $e) {
            $results['storage'] = ['status' => 'fail', 'writable' => false];
        }

        // 3. Verificación de Cache (Redis/Database)
        try {
            Cache::put('health_check', true, 10);
            $results['cache'] = ['status' => Cache::get('health_check') ? 'ok' : 'fail'];
        } catch (\Exception $e) {
            $results['cache'] = ['status' => 'fail'];
        }

        // 4. Metadatos de Release (Trazabilidad Punto 1.D)
        $totalLatency = round((microtime(true) - $start) * 1000, 2);
        $isHealthy = collect($results)->every(fn ($item) => $item['status'] === 'ok');

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'version' => trim(@file_get_contents(base_path('RELEASE_ID')) ?? 'v3.0.0'),
                'php_version' => PHP_VERSION,
            ],
            'infrastructure' => $results,
            'performance' => [
                'total_latency_ms' => $totalLatency,
            ],
        ], $isHealthy ? 200 : 503);
    }
}
