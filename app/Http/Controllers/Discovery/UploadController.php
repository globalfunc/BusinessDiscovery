<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    private const int MAX_FILE_BYTES = 15 * 1024 * 1024;

    private const int QUOTA_BYTES = 200 * 1024 * 1024;

    private const array IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    public function store(Request $request): JsonResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');
        $session = $this->currentSession($request);

        abort_if($session->status === 'submitted', 403, 'This discovery has already been submitted.');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_FILE_BYTES / 1024), 'mimes:csv,xlsx,pdf,docx,txt,png,jpg,jpeg,webp,svg'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        $usedBytes = (int) Upload::where('business_owner_id', $businessOwner->id)->sum('size');
        if ($usedBytes + $file->getSize() > self::QUOTA_BYTES) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => 'This upload would exceed the 200MB storage quota for this business.',
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $kind = in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'image' : 'document';

        $path = $file->store("uploads/{$businessOwner->id}", 'local');

        $thumbPath = $kind === 'image' && $extension !== 'svg'
            ? $this->makeThumbnail($path, $businessOwner->id)
            : null;

        $upload = Upload::create([
            'business_owner_id' => $businessOwner->id,
            'discovery_session_id' => $session->id,
            'phase' => 'phase_3',
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'kind' => $kind,
        ]);

        return response()->json([
            'upload' => $upload->toDiscoveryArray(),
            'quota' => ['used' => $usedBytes + $file->getSize(), 'limit' => self::QUOTA_BYTES],
        ]);
    }

    public function destroy(Request $request, Upload $upload): JsonResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        abort_unless($upload->business_owner_id === $businessOwner->id, 403);
        abort_if($this->currentSession($request)->status === 'submitted', 403, 'This discovery has already been submitted.');

        Storage::disk('local')->delete(array_filter([$upload->path, $upload->thumb_path]));
        $upload->delete();

        $usedBytes = (int) Upload::where('business_owner_id', $businessOwner->id)->sum('size');

        return response()->json([
            'deleted' => true,
            'quota' => ['used' => $usedBytes, 'limit' => self::QUOTA_BYTES],
        ]);
    }

    /**
     * Served only via signed temporary URL (§9/§10) — no session/ownership
     * check needed beyond the signature itself, matching the "signed
     * temporary URLs" requirement for private local-disk files.
     */
    public function show(Request $request, Upload $upload): StreamedResponse
    {
        $path = $request->boolean('thumb') && $upload->thumb_path ? $upload->thumb_path : $upload->path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $upload->original_name);
    }

    private function makeThumbnail(string $path, int $businessOwnerId): ?string
    {
        $manager = ImageManager::usingDriver(GdDriver::class);

        try {
            $image = $manager->decodePath(Storage::disk('local')->path($path));
        } catch (\Throwable) {
            return null;
        }

        $image->scaleDown(width: 400, height: 400);

        $pathInfo = pathinfo($path);
        $thumbPath = "uploads/{$businessOwnerId}/{$pathInfo['filename']}_thumb.{$pathInfo['extension']}";

        Storage::disk('local')->put($thumbPath, (string) $image->encode());

        return $thumbPath;
    }

    private function currentSession(Request $request): DiscoverySession
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        return DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();
    }
}
