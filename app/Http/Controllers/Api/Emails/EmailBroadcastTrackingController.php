<?php

namespace App\Http\Controllers\Api\Emails;

use App\Http\Controllers\Controller;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailBroadcastTrackingController extends Controller
{
    /**
     * Track email open.
     */
    public function trackOpen(Request $request, $trackingId, $recipientId)
    {
        try {
            $broadcast = EmailBroadcast::where('tracking_id', $trackingId)->firstOrFail();
            $recipient = EmailBroadcastRecipient::where('id', $recipientId)
                ->where('email_broadcast_id', $broadcast->id)
                ->firstOrFail();

            // Mark as opened
            if (!$recipient->opened) {
                $recipient->markAsOpened();
                $broadcast->increment('opened_count');
            } else {
                $recipient->increment('open_count');
            }

            // Return 1x1 transparent pixel
            return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
                ->header('Content-Type', 'image/gif')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to track email open', [
                'tracking_id' => $trackingId,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
            ]);

            // Still return tracking pixel to avoid broken images
            return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
                ->header('Content-Type', 'image/gif');
        }
    }

    /**
     * Track link click.
     */
    public function trackClick(Request $request, $trackingId, $recipientId)
    {
        try {
            $broadcast = EmailBroadcast::where('tracking_id', $trackingId)->firstOrFail();
            $recipient = EmailBroadcastRecipient::where('id', $recipientId)
                ->where('email_broadcast_id', $broadcast->id)
                ->firstOrFail();

            // Mark as clicked
            if (!$recipient->clicked) {
                $recipient->markAsClicked();
                $broadcast->increment('clicked_count');
            } else {
                $recipient->increment('click_count');
            }

            // Redirect to original URL
            $url = $request->input('url');
            if ($url) {
                return redirect($url);
            }

            return response()->json(['message' => 'Click tracked']);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to track click', [
                'tracking_id' => $trackingId,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to track click',
            ], 500);
        }
    }

    /**
     * Unsubscribe recipient.
     */
    public function unsubscribe($recipientId)
    {
        try {
            $recipient = EmailBroadcastRecipient::findOrFail($recipientId);

            // You might want to add an unsubscribe flag to the user model
            // For now, we'll just update the recipient metadata
            $metadata = $recipient->metadata ?? [];
            $metadata['unsubscribed_at'] = now();
            $recipient->update(['metadata' => $metadata]);

            return view('emails.unsubscribed', [
                'recipient' => $recipient,
            ]);

        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to unsubscribe', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
            ]);

            return view('errors.500');
        }
    }
}
