<?php

use Illuminate\Support\Facades\Http;

// Smallest valid PNG (1x1 transparent pixel)
const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

const VALID_CARDTRADER_IMAGE_URL = 'https://cardtrader.com/uploads/blueprints/image/123/luffy.png';

function imageProxyUrl(string $remoteUrl): string
{
    return '/api/image-proxy?url='.urlencode($remoteUrl);
}

test('it serves a CardTrader image with hardened headers', function () {
    Http::fake([
        'cardtrader.com/*' => Http::response(base64_decode(ONE_PIXEL_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
});

test('it rejects URLs that are not exactly CardTrader https uploads', function (string $maliciousUrl) {
    Http::fake();

    $response = $this->get(imageProxyUrl($maliciousUrl));

    $response->assertForbidden();
    Http::assertNothingSent();
})->with([
    'plain http' => 'http://cardtrader.com/uploads/a.png',
    'allowed host only in query string' => 'https://evil.tld/x.html?cardtrader.com',
    'allowed host as subdomain prefix' => 'https://cardtrader.com.evil.tld/uploads/a.png',
    'credentials trick' => 'https://cardtrader.com@evil.tld/uploads/a.png',
    'custom port' => 'https://cardtrader.com:8443/uploads/a.png',
    'path outside uploads' => 'https://cardtrader.com/admin',
    'path traversal' => 'https://cardtrader.com/uploads/../admin',
    'encoded path traversal' => 'https://cardtrader.com/uploads/%2e%2e/admin',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/?cardtrader.com',
    'internal elasticsearch' => 'http://elasticsearch:9200/_search?cardtrader.com',
]);

test('it does not serve non image content even with an image content type', function () {
    Http::fake([
        'cardtrader.com/*' => Http::response('<script>alert(document.cookie)</script>', 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL));

    $response->assertNotFound();
});

test('it does not follow redirects', function () {
    Http::fake([
        'cardtrader.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);

    $response = $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL));

    $response->assertNotFound();
    Http::assertSentCount(1);
});

test('it rejects images bigger than the configured limit', function () {
    config(['ocr.image_proxy.max_image_bytes' => 10]);

    Http::fake([
        'cardtrader.com/*' => Http::response(base64_decode(ONE_PIXEL_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL));

    $response->assertNotFound();
});

test('it is rate limited per client', function () {
    config(['ocr.image_proxy.requests_per_minute' => 2]);

    Http::fake([
        'cardtrader.com/*' => Http::response(base64_decode(ONE_PIXEL_PNG_BASE64), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL))->assertOk();
    $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL))->assertOk();
    $this->get(imageProxyUrl(VALID_CARDTRADER_IMAGE_URL))->assertTooManyRequests();
});
