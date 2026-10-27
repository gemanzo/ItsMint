<?php

namespace App\Console\Commands;

use App\Jobs\UpdateBlueprintPriceJob;
use App\Models\Blueprint;
use App\Models\Expansion;
use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MirrorCTDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:mirror-ct-database {--quick : Skip fetching games and expansions, only process existing ones for blueprints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mirror the CardTrader database (Games, Expansions, Blueprints) and queue price updates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('services.cardtrader.api_token');
        $baseApiUrl = config('services.cardtrader.api_base_url');
        $opGameID = config('services.cardtrader.one_piece_game_id');

        $gamesUrl = $baseApiUrl.'/games';
        $expansionsUrl = $baseApiUrl.'/expansions';
        $blueprintsUrl = $baseApiUrl.'/blueprints/export';

        $httpClient = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        if (empty($token) || empty($baseApiUrl)) {
            $this->error('CardTrader API token or base URL is not configured. Please check your .env and config/services.php.');

            return Command::FAILURE;
        }

        if (! $this->option('quick')) {
            $response = $httpClient->get($gamesUrl);
            if (! $response->successful()) {
                $this->error("Failed to fetch games. Status: {$response->status()}");
                Log::error('CardTrader API Error - Games', ['status' => $response->status(), 'body' => $response->body()]);

                return Command::FAILURE;
            }
            $games = $response->json();
            foreach ($games['array'] as $gameData) {
                // get only one piece game
                if ($gameData['id'] == $opGameID) {
                    Game::updateOrCreate([
                        'ct_id' => $gameData['id'],
                    ], [
                        'name' => $gameData['name'],
                        'display_name' => $gameData['display_name'],
                    ]);
                }
            }

            $response = $httpClient->get($expansionsUrl);
            if (! $response->successful()) {
                $this->error("Failed to fetch expansions. Status: {$response->status()}");
                Log::error('CardTrader API Error - Expansions', ['status' => $response->status(), 'body' => $response->body()]);

                return Command::FAILURE;
            }
            $expansionsData = $response->json();
            foreach ($expansionsData as $expData) {
                $localGame = Game::where('ct_id', $expData['game_id'])->first();
                if ($localGame) {
                    Expansion::updateOrCreate([
                        'ct_id' => $expData['id'],
                    ], [
                        'name' => $expData['name'],
                        'code' => $expData['code'],
                        'game_id' => $localGame->id,
                    ]);
                }
            }
        }

        // Process Blueprints for One Piece (or all expansions if $opGameID is not set)
        $expansionsToProcess = $opGameID ?
            Expansion::whereHas('game', fn ($query) => $query->where('ct_id', $opGameID))->get() :
            Expansion::all();

        if ($expansionsToProcess->isEmpty()) {
            $this->warn('No expansions found to process for blueprints. If targeting One Piece, ensure CARDTRADER_ONE_PIECE_GAME_ID is correct and the game/expansions exist.');

            return Command::SUCCESS;
        }

        foreach ($expansionsToProcess as $expansion) {
            $response = $httpClient->get($blueprintsUrl.'?expansion_id='.$expansion->ct_id);

            if (! $response->successful()) {
                $this->error("Failed to fetch blueprints for expansion {$expansion->name}. Status: {$response->status()}");
                Log::error('CardTrader API Error - Blueprints', ['expansion_ct_id' => $expansion->ct_id, 'status' => $response->status(), 'body' => $response->body()]);

                continue; // Skip to next expansion
            }

            $blueprintsApiData = collect($response->json())->filter(fn ($bp) => $bp['category_id'] == config('services.cardtrader.one_piece_single_card_category_id'));
            if (empty($blueprintsApiData)) {
                continue;
            }

            foreach ($blueprintsApiData as $bpData) {
                $localBlueprint = Blueprint::updateOrCreate([
                    'ct_id' => $bpData['id'],
                ], [
                    'name' => $bpData['name'],
                    'version' => $bpData['version'] ?? null,
                    'ct_category_id' => $bpData['category_id'],
                    'expansion_id' => $expansion->id,
                    'properties' => [
                        'fixed_properties' => $bpData['fixed_properties'] ?? [],
                        'editable_properties' => $bpData['editable_properties'] ?? [],
                    ],
                    'card_market_ids' => $bpData['card_market_ids'] ?? null,
                    'tcg_player_id' => $bpData['tcg_player_id'] ?? null,
                    'scryfall_id' => $bpData['scryfall_id'] ?? null,
                    'image_url' => $bpData['image_url'] ?? null,
                ]);

                // New cards get their prices right away; the others are updated by the
                // nightly price batch (itsmint:queue-price-updates). Queuing every card
                // here too would waste the CardTrader rate limit on duplicate jobs.
                if ($localBlueprint->wasRecentlyCreated) {
                    UpdateBlueprintPriceJob::dispatch($localBlueprint);
                }
            }
        }

        return Command::SUCCESS;
    }
}
