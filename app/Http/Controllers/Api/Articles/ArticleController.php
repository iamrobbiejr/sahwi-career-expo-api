<?php

namespace App\Http\Controllers\Api\Articles;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::with('author:id,name,email');

        // Filter by published status
        if ($request->has('published')) {
            $query->where('published', $request->boolean('published'));
        }

        // Filter by tag
        if ($request->has('tag')) {
            $query->withTag($request->tag);
        }

        // Filter by author
        if ($request->has('author_id')) {
            $query->where('author_id', $request->author_id);
        }

        // Search in title and body
        if ($request->has('search')) {
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

        $article->update($request->only([
            'title',
            'body',
            'published',
            'allow_comments',
            'tags'
        ]));

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
        $article->update([
            'published' => !$article->published
        ]);

        return response()->json([
            'message' => $article->published ? 'Article published' : 'Article unpublished',
            'data' => $article
        ]);
    }
}
