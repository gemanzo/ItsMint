<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class ImageProxyController extends Controller
{
    /**
     * Proxy images from CardTrader to avoid CORS issues
     */
    public function proxy(Request $request): Response
    {
        $url = $request->query('url');
        $proxyConfig = config('ocr.image_proxy');

        if (!$url) {
            return response('Missing URL parameter', 400);
        }

        // Validate against allowed domains
        $isAllowed = false;
        foreach ($proxyConfig['allowed_domains'] as $domain) {
            if (str_contains($url, $domain)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return response('Invalid image source', 403);
        }

        try {
            // Cache images for configured duration
            $cacheKey = 'image_proxy_' . md5($url);
            $cacheDuration = $proxyConfig['cache_duration'];

            $imageData = Cache::remember($cacheKey, $cacheDuration, function () use ($url) {
                $response = Http::timeout(10)->get($url);

                if (!$response->successful()) {
                    return null;
                }

                return [
                    'content' => $response->body(),
                    'content_type' => $response->header('Content-Type', 'image/jpeg')
                ];
            });

            if (!$imageData) {
                return response('Image not found', 404);
            }

            return response($imageData['content'])
                ->header('Content-Type', $imageData['content_type'])
                ->header('Cache-Control', 'public, max-age=' . $cacheDuration)
                ->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            Log::warning('Image proxy failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return response('Failed to load image', 500);
        }
    }
}
