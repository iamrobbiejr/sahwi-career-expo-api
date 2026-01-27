<?php

namespace App\Http\Controllers\Api\Communications;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Download a message attachment.
     */
    public function download(Request $request, $messageId, $index)
    {
        try {
            $userId = Auth::id();
            $message = Message::findOrFail($messageId);
            $thread = $message->thread;

            // Check if user is member of the thread
            if (!$thread->hasMember($userId)) {
                return response()->json([
                    'error' => 'Unauthorized access to attachment',
                ], 403);
            }

            $attachments = $message->attachments;

            if (empty($attachments) || !isset($attachments[$index])) {
                return response()->json([
                    'error' => 'Attachment not found',
                ], 404);
            }

            $attachment = $attachments[$index];
            $path = $attachment['path'];
            $name = $attachment['name'] ?? 'attachment';

            if (!Storage::disk('private')->exists($path)) {
                return response()->json([
                    'error' => 'File not found on server',
                ], 404);
            }

            return Storage::disk('private')->download($path, $name);

        } catch (Exception $e) {
            Log::channel('threads')->error('Failed to download attachment', [
                'message_id' => $messageId,
                'index' => $index,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to download attachment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
