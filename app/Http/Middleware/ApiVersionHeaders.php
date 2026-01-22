<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string $version): Response
    {
        $response = $next($request);

        $config = config("api.versions.$version");

        if (!$config) {
            return $response;
        }

        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Release', $config['release']);

        if ($config['deprecated'] === true) {
            $response->headers->set('X-API-Deprecated', 'true');

            if (!empty($config['deprecation_message'])) {
                $response->headers->set(
                    'X-API-Deprecation-Message',
                    $config['deprecation_message']
                );
            }
        } else {
            $response->headers->set('X-API-Deprecated', 'false');
        }

        return $response;
    }
}
