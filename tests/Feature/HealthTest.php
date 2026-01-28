<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok_or_degraded(): void
    {
        $res = $this->getJson('/api/health');

        if ($res->status() === 503) {
            $res->assertJsonPath('status', 'degraded');
        } else {
            $res->assertStatus(200);
            $res->assertJsonPath('status', 'ok');
        }

        $res->assertJsonStructure([
            'status',
            'timestamp',
            'app' => ['name', 'env', 'debug', 'version'],
            'db' => ['ok', 'latency_ms', 'driver'],
            'cache' => ['ok'],
            'storage' => ['ok'],
        ]);
    }
}
