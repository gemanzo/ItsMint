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
        $url = config('services.cardmarket.op_price_guide_url') ?: env('CARDMARKET_OP_PRICE_GUIDE');
        $path = config('services.cardmarket.op_price_guide_path') ?: storage_path('app/public/cardmarket/price_guide_18.json');

        if (! $url) {
            $this->error('Missing Cardmarket guide URL.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));

        $this->info('Downloading: '.$url);
        $resp = Http::timeout(60)->get($url);
        if (! $resp->successful()) {
            $this->error('Download failed. Status: '.$resp->status());

            return self::FAILURE;
        }

        File::put($path, $resp->body());
        $this->info('Saved to: '.$path);

        return self::SUCCESS;
    }
}
