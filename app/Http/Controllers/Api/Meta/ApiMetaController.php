<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiMetaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/meta",
     *     summary="API Metadata",
     *     description="Get API version and environment information",
     *     operationId="getApiMeta",
     *     tags={"Meta"},
     *     @OA\Response(
     *         response=200,
     *         description="API metadata retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="EduGate"),
     *             @OA\Property(property="environment", type="string", example="production"),
     *             @OA\Property(property="version", type="string", example="v1"),
     *             @OA\Property(property="release", type="string", example="1.0.0"),
     *             @OA\Property(property="deprecated", type="boolean", example=false),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-15T10:30:00Z")
     *         )
     *     )
     * )
     */
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
