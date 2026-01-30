<?php

namespace App\Http\Controllers\Api\Files;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    public function uploadVerificationDocs(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:pdf,jpg,png,jpeg|max:5120', // Max 5MB each
        ]);
        $uploadedUrls = [];
        foreach ($request->file('files') as $file) {
            // Store file in public disk under verification_docs/
            $path = $file->store('verification_docs', 'public');
            // Generate full URL
            $url = Storage::disk('public')->url($path);
            $uploadedUrls[] = $url;
        }
        return response()->json([
            'message' => 'Files uploaded successfully.',
            'urls' => $uploadedUrls
        ]);
    }

    public function uploadBanner(Request $request)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // Max 5MB
        ]);

        try {
            $path = $request->file('banner')->store('events/banners', 'public');

            // Construct the URL manually to include the /storage/app/public path
            $url = asset('storage/app/public/' . $path);

            return response()->json([
                'message' => 'Banner uploaded successfully.',
                'url' => $url
            ]);
        } catch (Exception $e) {
            Log::channel('files')->error($e->getMessage());
            return response()->json([
                'message' => 'Failed to upload banner.',
                'error' => $e->getMessage()
            ], 500);

        }
    }

}
