<?php

namespace App\Http\Controllers\Api;

use App\Support\PublicOrigin;
use Illuminate\Http\JsonResponse;

class DeploymentInfoController
{
    public function __invoke(): JsonResponse
    {
        $buildSha = trim((string) env('BUILD_SHA'));
        $buildTime = trim((string) env('BUILD_TIME'));

        return response()->json([
            'app_name' => config('app.name'),
            'app_environment' => app()->environment(),
            'app_version' => config('app.version', env('APP_VERSION', '1.0.0')),
            'public_host' => PublicOrigin::host(),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'build_sha' => $buildSha !== '' ? $buildSha : null,
            'build_time' => $buildTime !== '' ? $buildTime : null,
        ]);
    }
}
