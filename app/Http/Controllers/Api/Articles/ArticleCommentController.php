<?php

namespace App\Http\Controllers\Api\Articles;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleCommentController extends Controller
{
    /**
     * Display all comments for an article
     */
    public function index(Article $article): JsonResponse
    {
        $comments = $article->comments()
            ->with([
                'author:id,name,email',
                'replies' => function($query) {
                    $query->where('status', 'visible')
                        ->with('author:id,name,email')
                        ->latest();
                }
            ])
            ->rootLevel()
            ->where('status', 'visible')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, Article $article): JsonResponse
    {
        // Check if the article allows comments
        if (!$article->allow_comments) {
            return response()->json([
                'message' => 'Comments are disabled for this article'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content_message' => 'required|string|min:1|max:5000',
            'parent_comment_id' => 'nullable|exists:article_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate parent comment belongs to same article
        if ($request->parent_comment_id) {
            $parentComment = ArticleComment::find($request->parent_comment_id);
            if ($parentComment->article_id !== $article->id) {
                return response()->json([
                    'message' => 'Parent comment does not belong to this article'
                ], 422);
            }
        }

        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'author_id' => auth()->id(),
            'parent_comment_id' => $request->parent_comment_id,
            'content' => $request->content_message,
            'status' => 'visible',
        ]);

        $comment->load('author:id,name,email');

        return response()->json([
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    /**
     * Display a specific comment
     */
    public function show(Article $article, ArticleComment $comment): JsonResponse
    {
        // Ensure comment belongs to article
        if ($comment->article_id !== $article->id) {
            return response()->json([
                'message' => 'Comment not found for this article'
            ], 404);
        }

        $comment->load([
            'author:id,name,email',
            'visibleReplies.author:id,name,email'
        ]);

        return response()->json($comment);
    }

    /**
     * Update a comment
     */
    public function update(Request $request, Article $article, ArticleComment $comment): JsonResponse
    {
        // Ensure comment belongs to article
        if ($comment->article_id !== $article->id) {
            return response()->json([
                'message' => 'Comment not found for this article'
            ], 404);
        }

        // Check authorization (optional)
        // $this->authorize('update', $comment);

        $validator = Validator::make($request->all(), [
            'content_message' => 'required|string|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content_message
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $comment
        ]);
    }

    /**
     * Delete a comment (soft delete by status)
     */
    public function destroy(Article $article, ArticleComment $comment): JsonResponse
    {
        if ($comment->article_id !== $article->id) {
            return response()->json([
                'message' => 'Comment not found for this article'
            ], 404);
        }

        // Option 1: Mark as deleted
        $comment->update(['status' => 'deleted']);

        // Option 2: Permanently delete (uncomment if preferred)
        // $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Update comment status (for moderation)
     */
    public function updateStatus(Request $request, Article $article, ArticleComment $comment): JsonResponse
    {
        if ($comment->article_id !== $article->id) {
            return response()->json([
                'message' => 'Comment not found for this article'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:visible,hidden,flagged,deleted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Comment status updated successfully',
            'data' => $comment
        ]);
    }

    /**
     * Get replies for a specific comment
     */
    public function replies(Article $article, ArticleComment $comment): JsonResponse
    {
        if ($comment->article_id !== $article->id) {
            return response()->json([
                'message' => 'Comment not found for this article'
            ], 404);
        }

        $replies = $comment->replies()
            ->with('author:id,name,email')
            ->where('status', 'visible')
            ->latest()
            ->paginate(10);

        return response()->json($replies);
    }
}
