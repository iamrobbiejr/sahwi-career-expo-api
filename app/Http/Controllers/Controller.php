<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="EduGate API Documentation",
 *     description="Comprehensive API documentation for the EduGate platform. This API provides endpoints for event management, user authentication, registrations, payments, messaging, forums, articles, and more.",
 *     @OA\Contact(
 *         email="support@sahwi-career-expo.com"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://sahwi-career-expo.com/license"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Enter your bearer token in the format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and profile management endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Events",
 *     description="Event creation, management, and listing endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Event Registrations",
 *     description="Event registration and attendance management"
 * )
 *
 * @OA\Tag(
 *     name="Payments",
 *     description="Payment processing, gateways, and transaction management"
 * )
 *
 * @OA\Tag(
 *     name="Tickets",
 *     description="Event ticket management and scanning"
 * )
 *
 * @OA\Tag(
 *     name="Messaging",
 *     description="Direct messaging and thread management"
 * )
 *
 * @OA\Tag(
 *     name="Forums",
 *     description="Forum, post, and comment management"
 * )
 *
 * @OA\Tag(
 *     name="Articles",
 *     description="Article creation, publishing, and commenting"
 * )
 *
 * @OA\Tag(
 *     name="Organizations",
 *     description="Organization management and search"
 * )
 *
 * @OA\Tag(
 *     name="Admin",
 *     description="Administrative endpoints for user management and reports"
 * )
 *
 * @OA\Tag(
 *     name="Donations",
 *     description="Donation campaigns and contribution management"
 * )
 *
 * @OA\Tag(
 *     name="Conference Calls",
 *     description="Virtual conference call management"
 * )
 *
 * @OA\Tag(
 *     name="Email Broadcasts",
 *     description="Email campaign management and tracking"
 * )
 *
 * @OA\Tag(
 *     name="Meta",
 *     description="API metadata and version information"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
