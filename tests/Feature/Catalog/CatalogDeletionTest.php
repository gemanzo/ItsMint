<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function ownCard(Blueprint $blueprint): UserCard
{
    return UserCard::create([
        'user_id' => User::factory()->create()->id,
        'blueprint_id' => $blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
    ]);
}

/**
 * Run a statement expected to fail inside a savepoint: in PostgreSQL a failed
 * statement aborts the whole transaction the test runs in, unless rolled back.
 */
function attemptInSavepoint(Closure $statement): void
{
    DB::transaction($statement);
}

test('a catalog card owned by a user cannot be deleted', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    $ownedCopy = ownCard($blueprint);

    expect(fn () => attemptInSavepoint(fn () => $blueprint->deleteQuietly()))->toThrow(QueryException::class);
    expect($ownedCopy->fresh())->not->toBeNull();
});

test('deleting the expansion of an owned card is refused too', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    $ownedCopy = ownCard($blueprint);

    expect(fn () => attemptInSavepoint(fn () => $blueprint->expansion->delete()))->toThrow(QueryException::class);
    expect($ownedCopy->fresh())->not->toBeNull();
});

test('a catalog card nobody owns can still be deleted with its prices', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    CardPrice::create([
        'blueprint_id' => $blueprint->id,
        'condition' => 'Near Mint',
        'language' => 'en',
        'is_foil' => false,
        'price_cents' => 1000,
        'currency' => 'EUR',
        'source' => 'aggregate:cardtrader',
        'fetched_at' => now(),
    ]);

    $blueprint->deleteQuietly();

    expect(Blueprint::find($blueprint->id))->toBeNull();
    expect(CardPrice::where('blueprint_id', $blueprint->id)->exists())->toBeFalse();
});
