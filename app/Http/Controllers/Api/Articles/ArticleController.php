<?php

namespace App\Http\Controllers\Api\Articles;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\TrendingArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    protected TrendingArticleService $trendingService;

    public function __construct(TrendingArticleService $trendingService)
    {
        $this->trendingService = $trendingService;
    }

    /**
     * Display a listing of articles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::with('author:id,name,email');

        // Filter by published status
        if ($request->filled('published')) {
            $query->where('published', $request->boolean('published'));
        }

        // Filter by tag
        if ($request->filled('tag')) {
            $query->withTag($request->tag);
        }

        // Filter by author
        if ($request->filled('author_id')) {
            $query->where('author_id', $request->author_id);
        }

        // Search in title and body
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $articles = $query->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($articles);
    }

    /**
     * Store a newly created article
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'published' => 'boolean',
            'allow_comments' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $article = Article::create([
            'title' => $request->title,
            'body' => $request->body,
            'author_id' => auth()->id(), // Assumes authentication
            'published' => $request->get('published', false),
            'published_at' => $request->get('published', false) ? now() : null,
            'allow_comments' => $request->get('allow_comments', true),
            'tags' => $request->tags,
        ]);

        $article->load('author:id,name,email');

        return response()->json([
            'message' => 'Article created successfully',
            'data' => $article
        ], 201);
    }

    /**
     * Display the specified article
     */
    public function show(Article $article): JsonResponse
    {
        $article->recordView(auth()->user());

        $article->load([
            'author:id,name,email',
            'visibleComments' => function($query) {
                $query->with([
                    'author:id,name,email',
                    'visibleReplies.author:id,name,email'
                ])->latest();
            }
        ]);

        return response()->json($article);
    }

    /**
     * Update the specified article
     */
    public function update(Request $request, Article $article): JsonResponse
    {
        // Check authorization (optional - uncomment if using policies)
        // $this->authorize('update', $article);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'published' => 'boolean',
            'allow_comments' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'title',
            'body',
            'published',
            'allow_comments',
            'tags'
        ]);

        if (isset($data['published'])) {
            $data['published_at'] = $data['published'] ? now() : null;
        }

        $article->update($data);

        $article->load('author:id,name,email');

        return response()->json([
            'message' => 'Article updated successfully',
            'data' => $article
        ]);
    }

    /**
     * Remove the specified article
     */
    public function destroy(Article $article): JsonResponse
    {
        // Check authorization (optional)
        // $this->authorize('delete', $article);

        $article->delete();

        return response()->json([
            'message' => 'Article deleted successfully'
        ]);
    }

    /**
     * Publish/unpublish an article
     */
    public function togglePublish(Article $article): JsonResponse
    {
        $published = !$article->published;
        $article->update([
            'published' => $published,
            'published_at' => $published ? now() : null,
        ]);

        return response()->json([
            'message' => $article->published ? 'Article published' : 'Article unpublished',
            'data' => $article
        ]);
    }

    /**
     * Get trending articles
     */
    public function trending(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h');
        $limit = $request->get('limit', 10);
        $categories = $request->get('categories', []);

        $articles = $this->trendingService->getTrending($limit, $period, $categories);

        // Add user-specific data
        $user = $request->user();
        $articles->transform(function ($article) use ($user) {
            $article->has_liked = $article->hasLiked($user);
            $article->has_bookmarked = $article->hasBookmarked($user);
            return $article;
        });

        return response()->json([
            'articles' => $articles,
            'period' => $period,
        ]);
    }

    /**
     * Bookmark/unbookmark article
     */
    public function toggleBookmark(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'collection' => 'nullable|string|max:255',
        ]);

        $bookmarked = $article->toggleBookmark(
            $request->user(),
            $request->get('collection')
        );

        return response()->json([
            'bookmarked' => $bookmarked,
            'bookmarks_count' => $article->fresh()->bookmarks_count,
        ]);
    }

    /**
     * Like/unlike article
     */
    public function toggleLike(Request $request, Article $article): JsonResponse
    {
        $liked = $article->toggleLike($request->user());

        return response()->json([
            'liked' => $liked,
            'likes_count' => $article->fresh()->likes_count,
        ]);
    }

    /**
     * Share article
     */
    public function share(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'platform' => 'required|in:twitter,linkedin,facebook,whatsapp,email,copy',
        ]);

        $article->recordShare($request->platform, $request->user());

        return response()->json([
            'message' => 'Share recorded',
            'shares_count' => $article->fresh()->shares_count,
        ]);
    }

    /**
     * Get trending topics
     */
    public function trendingTopics(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $topics = $this->trendingService->getTrendingTopics($limit);

        return response()->json(['topics' => $topics]);
    }
}
