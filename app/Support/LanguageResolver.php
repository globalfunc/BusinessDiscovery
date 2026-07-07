<?php

namespace App\Support;

use App\Enums\Language;
use App\Models\BusinessOwner;
use Illuminate\Http\Request;

class LanguageResolver
{
    public const COOKIE_NAME = 'discovery_lang';

    /**
     * Resolution order: an explicit prior toggle (cookie) wins outright;
     * otherwise the BO's admin-provisioned default; otherwise the browser's
     * Accept-Language header (bg if present, else en). No IP geolocation,
     * no external calls, per §0.1.
     */
    public static function resolve(Request $request, ?BusinessOwner $businessOwner = null): Language
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);
        if ($cookieValue !== null) {
            $fromCookie = Language::tryFrom($cookieValue);
            if ($fromCookie !== null) {
                return $fromCookie;
            }
        }

        if ($businessOwner?->language !== null) {
            return $businessOwner->language;
        }

        return self::fromAcceptLanguageHeader($request->header('Accept-Language'));
    }

    private static function fromAcceptLanguageHeader(?string $header): Language
    {
        if ($header !== null && str_contains(strtolower($header), 'bg')) {
            return Language::Bulgarian;
        }

        return Language::English;
    }
}
