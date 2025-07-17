<?php

namespace Database\Seeders;

use App\Models\CardPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CardPriceHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $blueprintId = 864;
        $basePriceCents = 456;
        $startDate = Carbon::create(2025, 6, 15);
        $endDate = Carbon::create(2025, 7, 15);

        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            // Calculate a random price variation of +/- 15%
            $variation = (rand(-1500, 1500) / 10000); // Random float between -0.15 and 0.15
            $priceCents = round($basePriceCents * (1 + $variation));

            CardPrice::create([
                'blueprint_id' => $blueprintId,
                'price_cents' => $priceCents,
                'currency' => 'EUR',
                'condition' => 'Near Mint',
                'language' => 'en',
                'is_foil' => false,
                'source' => 'cardtrader_service_by_condition_and_language',
                'fetched_at' => $date->copy()->setTime(8, 26, 4), // Set a consistent time
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
