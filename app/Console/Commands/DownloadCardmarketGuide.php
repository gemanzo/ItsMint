<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

final class DownloadCardmarketGuide extends Command
{
    protected $signature = 'itsmint:download-cardmarket-guide';

    protected $description = 'Download Cardmarket One Piece price guide JSON to local storage';

    public function handle(): int
    {
        // Read from config only: env() returns null once the configuration is cached
        $url = config('services.cardmarket.op_price_guide_url');
        $path = config('services.cardmarket.op_price_guide_path') ?: storage_path('app/public/cardmarket/price_guide_18.json');

        if (! $url) {
            $this->error('Missing Cardmarket guide URL (CARDMARKET_OP_PRICE_GUIDE).');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));

        $this->info('Downloading: '.$url);
        $resp = Http::timeout(60)->get($url);
        if (! $resp->successful()) {
            $this->error('Download failed. Status: '.$resp->status());

            return self::FAILURE;
        }

        // Never replace a good guide with an error page or a truncated file
        $downloadedGuide = $resp->json();
        $isValidGuide = is_array($downloadedGuide)
            && is_array($downloadedGuide['priceGuides'] ?? null)
            && ! empty($downloadedGuide['createdAt']);

        if (! $isValidGuide) {
            $this->error('The downloaded file is not a valid Cardmarket price guide: the previous guide is kept.');

            return self::FAILURE;
        }

        // Write to a temporary file, then rename it: the ingest never reads a half-written guide
        $temporaryPath = $path.'.download';
        File::put($temporaryPath, $resp->body());
        File::move($temporaryPath, $path);

        $this->info('Saved to: '.$path);

        return self::SUCCESS;
    }
}
