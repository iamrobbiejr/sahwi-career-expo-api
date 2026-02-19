<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    /**
     * List audit logs with optional filters and pagination.
     *
     * @OA\Get(
     *     path="/v1/admin/audits",
     *     summary="List audit logs",
     *     description="Returns a paginated list of audit logs. Supports filtering by model type, user, event type, and date range. Admin only.",
     *     tags={"Admin"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=10, maximum=100, default=25)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="model_type", in="query", required=false, description="Filter by auditable model type (partial match)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", required=false, description="Filter by the user who performed the action", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="event", in="query", required=false, description="Filter by audit event type", @OA\Schema(type="string", enum={"created","updated","deleted","restored"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, description="Filter from this date (inclusive)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, description="Filter up to this date (inclusive, must be >= start_date)", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Audit logs retrieved successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'per_page' => 'nullable|integer|min:10|max:100',
                'page' => 'nullable|integer|min:1',
                'model_type' => 'nullable|string',
                'user_id' => 'nullable|integer',
                'event' => 'nullable|string|in:created,updated,deleted,restored',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $query = Audit::with('user');

            if ($request->filled('model_type')) {
                $query->where('auditable_type', 'LIKE', '%' . $request->model_type . '%');
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('event')) {
                $query->where('event', $request->event);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->input('per_page', 25);
            $audits = $query->paginate($perPage);

            return response()->json([
                'message' => 'Audit logs retrieved successfully.',
                'data' => $audits->items(),
                'meta' => [
                    'pagination' => [
                        'total' => $audits->total(),
                        'per_page' => $audits->perPage(),
                        'current_page' => $audits->currentPage(),
                        'last_page' => $audits->lastPage(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error('Error fetching audit logs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $request->all(),
            ]);

            return response()->json(['message' => 'Failed to fetch audit logs.'], 500);
        }
    }

    /**
     * Get a single audit log entry by ID.
     *
     * @OA\Get(
     *     path="/v1/admin/audits/{id}",
     *     summary="Get audit log details",
     *     description="Returns the full details of a single audit log entry, including old and new values. Admin only.",
     *     tags={"Admin"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Audit log ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Audit log retrieved successfully"),
     *     @OA\Response(response=404, description="Audit log not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $audit = Audit::with('user')->findOrFail($id);

            return response()->json([
                'message' => 'Audit log retrieved successfully.',
                'data' => [
                    'id' => $audit->id,
                    'event' => $audit->event,
                    'auditable_type' => $audit->auditable_type,
                    'auditable_id' => $audit->auditable_id,
                    'user' => $audit->user
                        ? ['id' => $audit->user->id, 'name' => $audit->user->name, 'email' => $audit->user->email]
                        : null,
                    'ip_address' => $audit->ip_address,
                    'user_agent' => $audit->user_agent,
                    'url' => $audit->url,
                    'old_values' => $audit->old_values,
                    'new_values' => $audit->new_values,
                    'created_at' => $audit->created_at->toDateTimeString(),
                ],
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Error fetching audit log ID {$id}: " . $e->getMessage());

            return response()->json(['message' => 'Audit log not found.'], 404);
        }
    }

    /**
     * Get the audit dashboard statistics for the last 30 days.
     *
     * @OA\Get(
     *     path="/v1/admin/audits/stats",
     *     summary="Get audit dashboard statistics",
     *     description="Returns summary statistics for the audit trail over the last 30 days, including activity counts, event type breakdown, daily trends, and most-audited models. Admin only.",
     *     tags={"Admin"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Audit stats retrieved successfully"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function stats(): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Audit statistics retrieved successfully.',
                'data' => $this->getDashboardStats(),
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error('Error fetching audit stats: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to fetch audit statistics.'], 500);
        }
    }

    /**
     * Get all distinct model types that have audit records (for filter dropdowns).
     *
     * @OA\Get(
     *     path="/v1/admin/audits/model-types",
     *     summary="List auditable model types",
     *     description="Returns all distinct model types that have at least one audit record. Useful for populating filter dropdowns. Admin only.",
     *     tags={"Admin"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Model types retrieved successfully"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function modelTypes(): JsonResponse
    {
        try {
            $modelTypes = Audit::select('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->get()
                ->map(function ($audit) {
                    $parts = explode('\\', $audit->auditable_type);
                    return [
                        'full_name' => $audit->auditable_type,
                        'name' => end($parts),
                    ];
                });

            return response()->json([
                'message' => 'Model types retrieved successfully.',
                'data' => $modelTypes,
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error('Error fetching audit model types: ' . $e->getMessage());

            return response()->json(['message' => 'Failed to fetch model types.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Build dashboard statistics for the last 30 days.
     */
    private function getDashboardStats(): array
    {
        $today = now()->startOfDay();
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();

        $totalActivities = Audit::where('created_at', '>=', $thirtyDaysAgo)->count();

        $todayActivities = Audit::where('created_at', '>=', $today)->count();

        $activeUsers = Audit::where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count();

        $eventCounts = Audit::where('created_at', '>=', $thirtyDaysAgo)
            ->select('event', DB::raw('COUNT(*) as count'))
            ->groupBy('event')
            ->get()
            ->pluck('count', 'event')
            ->toArray();

        $dailyActivity = Audit::where('created_at', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        $mostAuditedModels = Audit::where('created_at', '>=', $thirtyDaysAgo)
            ->select('auditable_type', DB::raw('COUNT(*) as count'))
            ->groupBy('auditable_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $parts = explode('\\', $item->auditable_type);
                return [
                    'model' => end($parts),
                    'count' => $item->count,
                ];
            });

        return [
            'period' => '30d',
            'total_activities' => $totalActivities,
            'today_activities' => $todayActivities,
            'active_users' => $activeUsers,
            'event_counts' => $eventCounts,
            'daily_activity' => $dailyActivity,
            'most_audited_models' => $mostAuditedModels,
        ];
    }
}
