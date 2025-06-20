<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Blueprint;
use App\Models\Expansion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Jobs\UpdateBlueprintPriceJob;
use App\Services\BlueprintPriceService;

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

        $gamesUrl = $baseApiUrl . '/games';
        $expansionsUrl = $baseApiUrl . '/expansions';
        $blueprintsUrl = $baseApiUrl . '/blueprints/export';

        $httpClient = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        if (empty($token) || empty($baseApiUrl)) {
            $this->error('CardTrader API token or base URL is not configured. Please check your .env and config/services.php.');
            return Command::FAILURE;
        }

        if (!$this->option('quick')) {
            $this->info("Fetching games...");
            $response = $httpClient->get($gamesUrl);
            if (!$response->successful()) {
                $this->error("Failed to fetch games. Status: {$response->status()}");
                Log::error('CardTrader API Error - Games', ['status' => $response->status(), 'body' => $response->body()]);
                return Command::FAILURE;
            }
            $games = $response->json();
            foreach ($games['array'] as $gameData) {
                //get only one piece game
                if ($gameData['id'] == $opGameID) {
                    Game::updateOrCreate([
                        'ct_id' => $gameData['id']
                    ], [
                        'name' => $gameData['name'],
                        'display_name' => $gameData['display_name'],
                    ]);
                }
            }
            $this->info(count($games) . " games processed.");

            $this->info("Fetching expansions...");
            $response = $httpClient->get($expansionsUrl);
            if (!$response->successful()) {
                $this->error("Failed to fetch expansions. Status: {$response->status()}");
                Log::error('CardTrader API Error - Expansions', ['status' => $response->status(), 'body' => $response->body()]);
                return Command::FAILURE;
            }
            $expansionsData = $response->json();
            foreach ($expansionsData as $expData) {
                $localGame = Game::where('ct_id', $expData['game_id'])->first();
                if ($localGame) {
                    Expansion::updateOrCreate([
                        'ct_id' => $expData['id']
                    ], [
                        'name' => $expData['name'],
                        'code' => $expData['code'],
                        'game_id' => $localGame->id,
                    ]);
                }
            }
            $this->info(count($expansionsData) . " expansions processed.");
        }

        // Process Blueprints for One Piece (or all expansions if $opGameID is not set)
        $this->info("Fetching blueprints and queueing price updates...");
        $expansionsToProcess = $opGameID ?
            Expansion::whereHas('game', fn($query) => $query->where('ct_id', $opGameID))->get() :
            Expansion::all();

        if ($expansionsToProcess->isEmpty()) {
            $this->warn("No expansions found to process for blueprints. If targeting One Piece, ensure CARDTRADER_ONE_PIECE_GAME_ID is correct and the game/expansions exist.");
            return Command::SUCCESS;
        }

        foreach ($expansionsToProcess as $expansion) {
            $this->info("Processing expansion: {$expansion->name} (CT ID: {$expansion->ct_id})");
            $response = $httpClient->get($blueprintsUrl . '?expansion_id=' . $expansion->ct_id);

            if (!$response->successful()) {
                $this->error("Failed to fetch blueprints for expansion {$expansion->name}. Status: {$response->status()}");
                Log::error('CardTrader API Error - Blueprints', ['expansion_ct_id' => $expansion->ct_id, 'status' => $response->status(), 'body' => $response->body()]);
                continue; // Skip to next expansion
            }

            $blueprintsApiData = $response->json();
            if (empty($blueprintsApiData)) {
                $this->line("No blueprints found for expansion: {$expansion->name}");
                continue;
            }

            foreach ($blueprintsApiData as $bpData) {
                $localBlueprint = Blueprint::updateOrCreate([
                    'ct_id' => $bpData['id']
                ], [
                    'name' => $bpData['name'],
                    'version' => $bpData['version'] ?? null,
                    'ct_category_id' => $bpData['category_id'],
                    'expansion_id' => $expansion->id,
                    'properties' => [
                        'fixed_properties' => $bpData['fixed_properties'] ?? [],
                        'editable_properties' => $bpData['editable_properties'] ?? []
                    ],
                    'card_market_ids' => $bpData['card_market_ids'] ?? null,
                    'tcg_player_id' => $bpData['tcg_player_id'] ?? null,
                    'scryfall_id' => $bpData['scryfall_id'] ?? null,
                    'image_url' => $bpData['image_url'] ?? null,
                ]);

                // Dispatch the job to update the price for this blueprint
                UpdateBlueprintPriceJob::dispatch($localBlueprint, new BlueprintPriceService());
                $this->line("Queued price update for blueprint: {$localBlueprint->name} (CT ID: {$localBlueprint->ct_id})");
            }
            $this->info("Processed " . count($blueprintsApiData) . " blueprints for expansion {$expansion->name}.");
        }

        $this->info('All blueprint price updates have been queued.');
        return Command::SUCCESS;
    }
}
