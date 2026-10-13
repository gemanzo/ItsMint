<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ImageProxyController extends Controller
{
    /**
     * Proxy CardTrader card images so the frontend can display them from our own origin.
     *
     * Security: this endpoint is public and downloads remote content server-side, so it is
     * a classic SSRF / content-injection vector. For this reason:
     * - only https URLs pointing exactly to an allowed host and path are accepted;
     * - redirects are never followed (they could point to internal addresses);
     * - only real images (detected from the bytes) up to a maximum size are served;
     * - the response can never be interpreted as an executable document.
     */
    public function proxy(Request $request): Response
    {
        $imageUrl = $request->query('url');
        $proxyConfig = config('ocr.image_proxy');

        if (! is_string($imageUrl) || $imageUrl === '') {
            return response('Missing URL parameter', 400);
        }

        if (! $this->isAllowedImageUrl($imageUrl, $proxyConfig)) {
            return response('Invalid image source', 403);
        }

        $cacheKey = 'image_proxy_'.md5($imageUrl);
        $cachedImage = Cache::get($cacheKey);

        if ($cachedImage !== null) {
            return $this->buildImageResponse($cachedImage, $proxyConfig['cache_duration']);
        }

        try {
            $downloadedImage = $this->downloadRemoteImage($imageUrl, $proxyConfig);
        } catch (\Throwable $exception) {
            Log::warning('Image proxy download failed', [
                'url' => $imageUrl,
                'error' => $exception->getMessage(),
            ]);

            return response('Failed to load image', 502);
        }

        if ($downloadedImage === null) {
            return response('Image not found', 404);
        }

        Cache::put($cacheKey, $downloadedImage, $proxyConfig['cache_duration']);

        return $this->buildImageResponse($downloadedImage, $proxyConfig['cache_duration']);
    }

    /**
     * Check that the URL is exactly an https CardTrader upload URL.
     */
    private function isAllowedImageUrl(string $imageUrl, array $proxyConfig): bool
    {
        $urlParts = parse_url($imageUrl);

        if ($urlParts === false) {
            return false;
        }

        $scheme = strtolower($urlParts['scheme'] ?? '');
        $host = strtolower($urlParts['host'] ?? '');
        $path = $urlParts['path'] ?? '';

        if ($scheme !== 'https') {
            return false;
        }

        // Credentials and custom ports are never part of a legitimate CardTrader image URL
        // (e.g. "https://cardtrader.com@evil.tld/" actually points to evil.tld)
        if (isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['port'])) {
            return false;
        }

        if (! in_array($host, $proxyConfig['allowed_hosts'], true)) {
            return false;
        }

        if (! str_starts_with($path, $proxyConfig['allowed_path_prefix'])) {
            return false;
        }

        // Reject path traversal attempts, also when URL-encoded (/uploads/%2e%2e/...)
        if (str_contains(rawurldecode($path), '..')) {
            return false;
        }

        return true;
    }

    /**
     * Download the remote image.
     *
     * Returns null when the remote resource is missing or is not an acceptable image.
     *
     * @return array{content: string, content_type: string}|null
     */
    private function downloadRemoteImage(string $imageUrl, array $proxyConfig): ?array
    {
        $maxImageBytes = $proxyConfig['max_image_bytes'];

        $remoteResponse = Http::timeout(10)
            ->connectTimeout(5)
            ->withOptions([
                // Never follow redirects: the target could be an internal address
                'allow_redirects' => false,
                // Abort early when the declared size is already too big
                'on_headers' => function (ResponseInterface $headersOnlyResponse) use ($maxImageBytes): void {
                    $declaredContentLength = (int) $headersOnlyResponse->getHeaderLine('Content-Length');

                    if ($declaredContentLength > $maxImageBytes) {
                        throw new RuntimeException('Remote image exceeds the maximum allowed size');
                    }
                },
            ])
            ->get($imageUrl);

        // Redirects (3xx) are treated as missing images on purpose
        if ($remoteResponse->status() !== 200) {
            return null;
        }

        $imageBytes = $remoteResponse->body();

        // The Content-Length header can be missing or wrong: check the real size too
        if (strlen($imageBytes) > $maxImageBytes) {
            Log::warning('Image proxy rejected an oversized image', ['url' => $imageUrl]);

            return null;
        }

        // Detect the type from the bytes instead of trusting the upstream Content-Type header
        $detectedContentType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($imageBytes);

        if (! in_array($detectedContentType, $proxyConfig['allowed_content_types'], true)) {
            Log::warning('Image proxy rejected a non-image response', [
                'url' => $imageUrl,
                'detected_content_type' => $detectedContentType,
            ]);

            return null;
        }

        return [
            'content' => $imageBytes,
            'content_type' => $detectedContentType,
        ];
    }

    /**
     * Build the response for a validated image with hardened headers.
     *
     * @param  array{content: string, content_type: string}  $image
     */
    private function buildImageResponse(array $image, int $cacheDurationSeconds): Response
    {
        return response($image['content'], 200, [
            'Content-Type' => $image['content_type'],
            'Cache-Control' => 'public, max-age='.$cacheDurationSeconds,
            // Prevent the browser from sniffing a different (executable) content type
            'X-Content-Type-Options' => 'nosniff',
            // Even if opened directly, the response can't run scripts or load other resources
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
