<?php

use App\Http\Controllers\Api\Admin\Events\ConferenceCallController;
use App\Http\Controllers\Api\Admin\Events\EventActivityController;
use App\Http\Controllers\Api\Admin\Events\EventController;
use App\Http\Controllers\Api\Admin\Events\EventPanelController;
use App\Http\Controllers\Api\Admin\Reports\ReportsController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\VerificationController;
use App\Http\Controllers\Api\Articles\ArticleCommentController;
use App\Http\Controllers\Api\Articles\ArticleController;
use App\Http\Controllers\Api\Auth;
use App\Http\Controllers\Api\Communications\AttachmentController;
use App\Http\Controllers\Api\Communications\MessageController;
use App\Http\Controllers\Api\Communications\ThreadController;
use App\Http\Controllers\Api\Emails\EmailBroadcastController;
use App\Http\Controllers\Api\Emails\EmailBroadcastTrackingController;
use App\Http\Controllers\Api\EventRegistrations\EventRegistrationController;
use App\Http\Controllers\Api\Files\FileUploadController;
use App\Http\Controllers\Api\Forums\ForumCommentController;
use App\Http\Controllers\Api\Forums\ForumController;
use App\Http\Controllers\Api\Forums\ForumPostController;
use App\Http\Controllers\Api\Me\RewardsHistoryController;
use App\Http\Controllers\Api\Me\StatsController;
use App\Http\Controllers\Api\Meta\ApiMetaController;
use App\Http\Controllers\Api\Organizations\OrganizationController;
use App\Http\Controllers\Api\Payments\PaymentController;
use App\Http\Controllers\Api\Payments\PaymentGatewayController;
use App\Http\Controllers\Api\Payments\TicketController;
use App\Http\Controllers\Api\Payments\WebhookController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\DonationCampaignController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\UniversityController;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')
    ->middleware('api.version:v1')
    ->group(function () {

    /*
    |--------------------------------------------------------------------------
    | API Meta / Version Info
    |--------------------------------------------------------------------------
    */
    Route::get('/meta', ApiMetaController::class);


        /*
       |--------------------------------------------------------------------------
       | Universities
       |--------------------------------------------------------------------------
       */

        Route::get('/universities', [UniversityController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Organizations
    |--------------------------------------------------------------------------
    */
        Route::prefix('organizations')->group(function () {
            Route::get('/search', [OrganizationController::class, 'search']);
            Route::get('/', [OrganizationController::class, 'index']);
            Route::get('/{organization}', [OrganizationController::class, 'show']);
        });

        /*
        |--------------------------------------------------------------------------
        | Auth Routes
        |--------------------------------------------------------------------------
        */
    Route::prefix('auth')->group(function () {
        Route::post('/register', Auth\RegisterController::class)
            ->middleware('throttle:auth');

        Route::post('/login', Auth\LoginController::class)
            ->middleware('throttle:auth');

        Route::post('/forgot-password', Auth\ForgotPasswordController::class)
            ->name('password.email')
            ->middleware('throttle:5,1');

        Route::post('/reset-password', Auth\ResetPasswordController::class)
            ->name('password.update');

        Route::put('/change-password', Auth\ChangePasswordController::class)
            ->name('password.change');
        Route::get(
            '/email/verify/{id}/{hash}',
            Auth\EmailVerificationController::class
        )->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [Auth\LoginController::class, 'logout']);

            Route::get('/profile', [Auth\ProfileController::class, 'show']);
            Route::put('/profile', [Auth\ProfileController::class, 'update']);

        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated User Stats
    |--------------------------------------------------------------------------
    */
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me/stats', StatsController::class);
            Route::get('/me/rewards/history', RewardsHistoryController::class);
        });

        /*
        |--------------------------------------------------------------------------
        | File Uploads
        |--------------------------------------------------------------------------
        */
    Route::prefix('files')->group(function () {
        Route::post(
            '/upload/verification-docs',
            [FileUploadController::class, 'uploadVerificationDocs']
        );

        Route::middleware('auth:sanctum')
            ->post('/events/upload-banner', [FileUploadController::class, 'uploadBanner'])
            ->middleware('auth:sanctum');
    });

        /*
          |--------------------------------------------------------------------------
          | Admin Routes
          |--------------------------------------------------------------------------
          */
        Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
            Route::get('/pending-verifications', [VerificationController::class, 'index']);
            Route::post('/verify-user/{userId}', [VerificationController::class, 'approve']);
            Route::post('/reject-user/{userId}', [VerificationController::class, 'reject']);
            Route::apiResource('users', UserManagementController::class)->parameters(['users' => 'userId']);
            Route::put('/users/{userId}/role', [UserManagementController::class, 'updateRole']);
            Route::patch('/users/{userId}/suspend', [UserManagementController::class, 'toggleSuspension']);

            // Role & Permission Management
            Route::get('/roles', [RolePermissionController::class, 'indexRoles']);
            Route::get('/permissions', [RolePermissionController::class, 'indexPermissions']);
            Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'update']);

            // Reports
            Route::prefix('reports')->group(function () {
                // Financial analysis — paid registrations
                Route::get('/financial', [ReportsController::class, 'financial']);
                Route::get('/financial/export', [ReportsController::class, 'financialExport']);

                // Payments summary
                Route::get('/payments-summary', [ReportsController::class, 'paymentsSummary']);
                Route::get('/payments-summary/export', [ReportsController::class, 'paymentsSummaryExport']);

                // Pending & Cancelled registrations
                Route::get('/pending-cancelled', [ReportsController::class, 'pendingCancelled']);
            });
        });

        /*
         |--------------------------------------------------------------------------
         | Events Routes
         |--------------------------------------------------------------------------
         */
        // Public or Authenticated Event Routes
        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index']); // List events
            Route::get('/upcoming', [EventController::class, 'homeIndex']);
            Route::get('/{eventId}', [EventController::class, 'show']); // Show a single event
        });
        // Protected Admin / Creator Routes
        Route::middleware(['auth:sanctum', 'can:events.create'])->group(function () {
            Route::apiResource('events', EventController::class)->except(['index', 'show']);
            Route::get('/admin/events', [EventController::class, 'adminIndex']); // List events
            Route::post('/events/bulk', [EventController::class, 'bulkStore']); // Create multiple panels/activities together
        });
        // Event Panels
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::apiResource('event-panels', EventPanelController::class)->except(['index']);
            Route::get('/events/{eventId}/panels', [EventPanelController::class, 'index']);
        });
        // Event Activities
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::apiResource('event-activities', EventActivityController::class)->except(['index']);
            Route::get('/events/{eventId}/activities', [EventActivityController::class, 'index']);
        });

        /*
         |--------------------------------------------------------------------------
         | Conference Calls Routes
         |--------------------------------------------------------------------------
         */
        Route::prefix('conference-calls')->middleware(['auth:sanctum'])->group(function () {
            Route::get('/', [ConferenceCallController::class, 'index']);
            Route::post('/', [ConferenceCallController::class, 'store']);
            Route::get('/upcoming', [ConferenceCallController::class, 'upcoming']);
            Route::get('/{id}', [ConferenceCallController::class, 'show']);
            Route::put('/{id}', [ConferenceCallController::class, 'update']);
            Route::delete('/{id}', [ConferenceCallController::class, 'destroy']);

            // Meeting actions
            Route::post('/{id}/start', [ConferenceCallController::class, 'start']);
            Route::post('/{id}/end', [ConferenceCallController::class, 'end']);
            Route::post('/{id}/cancel', [ConferenceCallController::class, 'cancel']);
            Route::get('/{id}/credentials', [ConferenceCallController::class, 'getCredentials']);
        });

        /*
        |--------------------------------------------------------------------------
        | Email Broadcast Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware(['auth:sanctum'])->prefix('broadcasts')->group(function () {
            Route::get('/', [EmailBroadcastController::class, 'index']);
            Route::post('/', [EmailBroadcastController::class, 'store']);
            Route::get('/{id}', [EmailBroadcastController::class, 'show']);
            Route::put('/{id}', [EmailBroadcastController::class, 'update']);
            Route::delete('/{id}', [EmailBroadcastController::class, 'destroy']);

            // Actions
            Route::post('/{id}/send', [EmailBroadcastController::class, 'send']);
            Route::post('/{id}/cancel', [EmailBroadcastController::class, 'cancel']);
            Route::get('/{id}/statistics', [EmailBroadcastController::class, 'statistics']);

            // Preview
            Route::post('/preview-recipients', [EmailBroadcastController::class, 'previewRecipients']);
        });

// Tracking routes (public)
        Route::prefix('broadcast')->group(function () {
            Route::get('/track/open/{tracking_id}/{recipient_id}', [EmailBroadcastTrackingController::class, 'trackOpen'])
                ->name('broadcast.track.open');
            Route::get('/track/click/{tracking_id}/{recipient_id}', [EmailBroadcastTrackingController::class, 'trackClick'])
                ->name('broadcast.track.click');
            Route::get('/unsubscribe/{recipient_id}', [EmailBroadcastTrackingController::class, 'unsubscribe'])
                ->name('broadcast.unsubscribe');
        });

        /*
|--------------------------------------------------------------------------
| Registration & Payments API Routes
|--------------------------------------------------------------------------
*/

        // Payment Gateways (public list)
        Route::get('payment-gateways', [PaymentGatewayController::class, 'index']);

        // Webhooks (no auth required)
        Route::post('webhooks/paynow', [WebhookController::class, 'paynow']);
        Route::post('webhooks/paypal', [WebhookController::class, 'paypal']);
        Route::post('webhooks/stripe', [WebhookController::class, 'stripe']);
        Route::post('webhooks/smilepay', [WebhookController::class, 'smilepay'])->name('webhooks.smilepay');

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('events/{event}/registrations', [EventRegistrationController::class, 'registrations']);
            Route::get('events/{event}/analytics', [EventRegistrationController::class, 'analytics']);

            // Payment Gateway Management
            Route::apiResource('payment-gateways', PaymentGatewayController::class)->except(['index']);

            // Event Registrations
            Route::prefix('events/{event}')->group(function () {
                // Individual registration
                Route::post('register', [EventRegistrationController::class, 'registerIndividual']);

                // Group registration (company_rep only)
                Route::post('register-group', [EventRegistrationController::class, 'registerGroup']);

                // Check registration status
                Route::get('registration-status', [EventRegistrationController::class, 'checkStatus']);
            });

            // User's Registrations
            Route::get('my-registrations', [EventRegistrationController::class, 'myRegistrations']);
            Route::get('check-registration/{event_id}', [EventRegistrationController::class, 'checkRegistration']);
            Route::get('registrations/{registration}', [EventRegistrationController::class, 'show']);
            Route::post('registrations/{registration}/cancel', [EventRegistrationController::class, 'cancel']);

            // Payments
            Route::post('payments/initiate', [PaymentController::class, 'initiate']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);
            Route::get('payments/{payment}/status', [PaymentController::class, 'status']);
            Route::post('payments/{payment}/verify', [PaymentController::class, 'verify']);
            Route::get('my-payments', [PaymentController::class, 'myPayments']);

            // Refunds (Admin only)
            Route::middleware(['role:admin'])->group(function () {
                Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
                Route::get('refunds', [PaymentController::class, 'refunds']);
            });

            // Tickets
            Route::get('tickets/{ticket}', [TicketController::class, 'show']);
            Route::get('tickets/{ticket}/download', [TicketController::class, 'download']);
            Route::post('tickets/{ticket}/resend', [TicketController::class, 'resend']);
            Route::get('my-tickets', [TicketController::class, 'myTickets']);

            // Ticket Scanning (Admin/Event Staff)
            Route::middleware(['role:admin'])->group(function () {
                Route::post('tickets/scan', [TicketController::class, 'scan']);
                Route::post('tickets/{ticket}/check-in', [TicketController::class, 'checkIn']);
            });
        });


        /*
|--------------------------------------------------------------------------
| Messaging & Forums API Routes
|--------------------------------------------------------------------------
*/

        Route::middleware(['auth:sanctum'])->group(function () {

            // ============================================
            // THREADS & MESSAGING ROUTES
            // ============================================

            Route::prefix('threads')->group(function () {
                // Thread Management
                Route::get('/', [ThreadController::class, 'index']);
                Route::post('/', [ThreadController::class, 'store']);
                Route::get('/{id}', [ThreadController::class, 'show']);
                Route::put('/{id}', [ThreadController::class, 'update']);
                Route::delete('/{id}', [ThreadController::class, 'destroy']);

                // Thread Actions
                Route::post('/{id}/members', [ThreadController::class, 'addMember']);
                Route::delete('/{id}/members', [ThreadController::class, 'removeMember']);
                Route::post('/{id}/leave', [ThreadController::class, 'leave']);
                Route::post('/{id}/mute', [ThreadController::class, 'toggleMute']);

                Route::get('/search/user', [ThreadController::class, 'searchUser']);
            });

            // Messages within Threads
            Route::prefix('threads/{threadId}/messages')->group(function () {
                Route::get('/', [MessageController::class, 'index']);
                Route::post('/', [MessageController::class, 'store']);
                Route::get('/{messageId}', [MessageController::class, 'show']);
                Route::put('/{messageId}', [MessageController::class, 'update']);
                Route::delete('/{messageId}', [MessageController::class, 'destroy']);

                // Message Actions
                Route::post('/{messageId}/reactions', [MessageController::class, 'addReaction']);
                Route::delete('/{messageId}/reactions', [MessageController::class, 'removeReaction']);
                Route::get('/search', [MessageController::class, 'search']);
            });

            // Message Attachments
            Route::get('message-attachments/{messageId}/{index}', [AttachmentController::class, 'download']);

            // ============================================
            // FORUMS ROUTES
            // ============================================

            Route::prefix('forums')->group(function () {


                // Forum Management
                Route::get('/', [ForumController::class, 'index']);
                Route::post('/', [ForumController::class, 'store']);
                Route::get('/{id}', [ForumController::class, 'show']);
                Route::put('/{id}', [ForumController::class, 'update']);
                Route::delete('/{id}', [ForumController::class, 'destroy']);

                // Forum Membership
                Route::post('/{id}/join', [ForumController::class, 'join']);
                Route::post('/{id}/leave', [ForumController::class, 'leave']);
                Route::get('/{id}/members', [ForumController::class, 'members']);
                Route::post('/{id}/members/{userId}', [ForumController::class, 'removeMember']);
                Route::get('/{id}/membership', [ForumController::class, 'checkMembership']);
            });

            // Global Post Routes
            Route::get('/forums/posts/hottest-posts', [ForumPostController::class, 'hottest']);

            // Forum Posts
            Route::prefix('forums/{forumId}/posts')->group(function () {
                // Post Management
                Route::get('/', [ForumPostController::class, 'index']);
                Route::post('/', [ForumPostController::class, 'store']);
                Route::get('/{postId}', [ForumPostController::class, 'show']);
                Route::put('/{postId}', [ForumPostController::class, 'update']);
                Route::delete('/{postId}', [ForumPostController::class, 'destroy']);

                // Post Actions
                Route::post('/{postId}/pin', [ForumPostController::class, 'togglePin']);
                Route::post('/{postId}/lock', [ForumPostController::class, 'toggleLock']);
                Route::post('/{postId}/approve', [ForumPostController::class, 'approve']);
                Route::post('/{postId}/reject', [ForumPostController::class, 'reject']);
            });

            // Forum Comments
            Route::prefix('forums/{forumId}/posts/{postId}/comments')->group(function () {
                // Comment Management
                Route::get('/', [ForumCommentController::class, 'index']);
                Route::post('/', [ForumCommentController::class, 'store']);
                Route::get('/{commentId}', [ForumCommentController::class, 'show']);
                Route::put('/{commentId}', [ForumCommentController::class, 'update']);
                Route::delete('/{commentId}', [ForumCommentController::class, 'destroy']);

                // Comment Actions
                Route::post('/{commentId}/like', [ForumCommentController::class, 'toggleLike']);
                Route::post('/{commentId}/approve', [ForumCommentController::class, 'approve']);
                Route::post('/{commentId}/reject', [ForumCommentController::class, 'reject']);
                Route::get('/{commentId}/replies', [ForumCommentController::class, 'replies']);
            });
        });


        /*
|--------------------------------------------------------------------------
| Articles Routes
|--------------------------------------------------------------------------
*/
        // Public routes (no authentication required)
        Route::prefix('articles')->group(function () {
            Route::get('/', [ArticleController::class, 'index']);
            Route::get('/trending', [ArticleController::class, 'trending']);
            Route::get('/trending-topics', [ArticleController::class, 'trendingTopics']);
            Route::get('/{article}', [ArticleController::class, 'show']);
            Route::post('/{article}/share', [ArticleController::class, 'share']);

            // Article comments - public reading
            Route::get('/{article}/comments', [ArticleCommentController::class, 'index']);
            Route::get('/{article}/comments/{comment}', [ArticleCommentController::class, 'show']);
            Route::get('/{article}/comments/{comment}/replies', [ArticleCommentController::class, 'replies']);
        });


// Protected routes (require authentication)
        Route::middleware('auth:sanctum')->group(function () {

            // Article management
            Route::prefix('articles')->group(function () {
                Route::post('/', [ArticleController::class, 'store']);
                Route::put('/{article}', [ArticleController::class, 'update']);
                Route::delete('/{article}', [ArticleController::class, 'destroy']);
                Route::patch('/{article}/toggle-publish', [ArticleController::class, 'togglePublish']);
                Route::post('/{article}/bookmark', [ArticleController::class, 'toggleBookmark']);
                Route::post('/{article}/like', [ArticleController::class, 'toggleLike']);

                // Comment management
                Route::post('/{article}/comments', [ArticleCommentController::class, 'store']);
                Route::put('/{article}/comments/{comment}', [ArticleCommentController::class, 'update']);
                Route::delete('/{article}/comments/{comment}', [ArticleCommentController::class, 'destroy']);
                Route::patch('/{article}/comments/{comment}/status', [ArticleCommentController::class, 'updateStatus']);
            });
        });

        /*
|--------------------------------------------------------------------------
| Donation Campaign Routes
|--------------------------------------------------------------------------
*/

        // Public routes - Campaigns
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [DonationCampaignController::class, 'index']);
            Route::get('/{campaign}', [DonationCampaignController::class, 'show']);
            Route::get('/{campaign}/statistics', [DonationCampaignController::class, 'statistics']);
            Route::get('/{campaign}/top-donors', [DonationCampaignController::class, 'topDonors']);
            Route::get('/{campaign}/recent-donations', [DonationCampaignController::class, 'recentDonations']);
        });

// Public routes - Donations
        Route::prefix('donations')->group(function () {
            Route::get('/', [DonationController::class, 'index']);
            Route::get('/{donation}', [DonationController::class, 'show']);
            Route::get('/statistics/overall', [DonationController::class, 'statistics']);
        });

// Protected routes (require authentication)
        Route::middleware('auth:sanctum')->group(function () {

            // Campaign management
            Route::prefix('campaigns')->group(function () {
                Route::post('/', [DonationCampaignController::class, 'store']);
                Route::put('/{campaign}', [DonationCampaignController::class, 'update']);
                Route::delete('/{campaign}', [DonationCampaignController::class, 'destroy']);
                Route::patch('/{campaign}/toggle-active', [DonationCampaignController::class, 'toggleActive']);
            });

            // Donation management
            Route::prefix('donations')->group(function () {
                Route::post('/', [DonationController::class, 'store']);
                Route::put('/{donation}', [DonationController::class, 'update']);
                Route::delete('/{donation}', [DonationController::class, 'destroy']);
                Route::patch('/{donation}/status', [DonationController::class, 'updateStatus']);
                Route::post('/{donation}/process-payment', [DonationController::class, 'processPayment']);
                Route::get('/my/history', [DonationController::class, 'myDonations']);
            });
        });


    });
