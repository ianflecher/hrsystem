<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Support\Facades\Http;
use Throwable;

class CentralAdminController extends Controller
{
    public function index()
    {
        PeopleAccess::admin();

        $services = collect(config('central_admin.services', []))
            ->map(function (array $service, string $key): array {
                $startedAt = microtime(true);
                $status = 'offline';
                $statusCode = null;

                try {
                    $response = Http::connectTimeout(1)
                        ->timeout(3)
                        ->withOptions(['allow_redirects' => false])
                        ->get(rtrim($service['health_url'], '/').'/');

                    $statusCode = $response->status();
                    $status = $statusCode >= 200 && $statusCode < 500 ? 'online' : 'error';
                } catch (Throwable) {
                    // A failed companion app should not prevent the control
                    // center from opening and showing the other systems.
                }

                return [
                    'key' => $key,
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'url' => $service['url'],
                    'icon' => $service['icon'],
                    'accent' => $service['accent'],
                    'status' => $status,
                    'status_code' => $statusCode,
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ];
            })
            ->values();

        $summary = [
            'online' => $services->where('status', 'online')->count(),
            'configured' => $services->count(),
            'checked_at' => now(),
        ];

        return view('admin.control-center', compact('services', 'summary'));
    }
}
