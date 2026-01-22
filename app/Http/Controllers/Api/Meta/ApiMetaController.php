<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ApiMetaController extends Controller
{
    public function __invoke(Request $request)
    {
        $version = config('api.current_version');
        $config = config("api.versions.$version");

        return response()->json([
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'version' => $version,
            'release' => $config['release'] ?? null,
            'deprecated' => $config['deprecated'] ?? false,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

}
