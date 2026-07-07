<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class BrandingController extends Controller
{
    /**
     * Extracts a small dominant-color palette from the BO's admin-set logo
     * (business_owners.logo_path, set at BO creation — see S1.3). Real
     * extraction, not a stub: downsamples the logo to a coarse grid, buckets
     * sampled pixels into similar colors, and returns the most frequent
     * buckets as hex swatches. Not a proper k-means/quantization — good
     * enough for "pick a preset from your logo" without pulling in an extra
     * dependency.
     */
    public function logoColors(Request $request): JsonResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        if (! $businessOwner->logo_path || ! Storage::disk('public')->exists($businessOwner->logo_path)) {
            return response()->json(['error' => 'no_logo', 'message' => 'No logo has been set for this business yet.'], 404);
        }

        $manager = ImageManager::usingDriver(GdDriver::class);

        try {
            $image = $manager->decodePath(Storage::disk('public')->path($businessOwner->logo_path));
        } catch (\Throwable) {
            return response()->json(['error' => 'unreadable', 'message' => 'Could not read the logo image.'], 422);
        }

        $image->scaleDown(width: 60, height: 60);

        $buckets = [];
        for ($x = 0; $x < $image->width(); $x++) {
            for ($y = 0; $y < $image->height(); $y++) {
                $color = $image->colorAt($x, $y);

                // Skip near-transparent pixels — rarely the "brand" color.
                if ($color->isTransparent()) {
                    continue;
                }

                [$r, $g, $b] = sscanf($color->toHex(), '%02x%02x%02x');

                $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                if ($luma > 245 || $luma < 12) {
                    continue;
                }

                // Quantize to steps of 32 per channel to merge near-identical colors.
                $key = implode(',', array_map(fn ($c) => (int) (round($c / 32) * 32), [$r, $g, $b]));
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        }

        arsort($buckets);
        $topColors = array_slice(array_keys($buckets), 0, 5);

        $hexColors = array_map(function (string $key) {
            [$r, $g, $b] = array_map('intval', explode(',', $key));

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }, $topColors);

        return response()->json(['colors' => $hexColors]);
    }
}
