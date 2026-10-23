<?php

namespace App\Http\Controllers;

use App\Services\RpgProfileCatalog;
use Illuminate\Http\Response;

class RpgAvatarController extends Controller
{
    public function __invoke(string $profile, RpgProfileCatalog $catalog): Response
    {
        abort_unless(ctype_digit($profile) && (int) $profile >= 1 && (int) $profile <= 4294967295, 404);
        return response($catalog->svg((int) $profile), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function sprite(string $profile, RpgProfileCatalog $catalog): Response
    {
        abort_unless(ctype_digit($profile) && (int) $profile >= 1 && (int) $profile <= 4294967295, 404);

        return response($catalog->spriteSvg((int) $profile), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
