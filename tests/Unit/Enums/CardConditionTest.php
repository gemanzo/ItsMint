<?php

use App\Enums\CardCondition;

test('codes, labels and CardTrader names are normalized to the canonical condition', function (string $rawCondition, CardCondition $expectedCondition) {
    expect(CardCondition::fromProviderValue($rawCondition))->toBe($expectedCondition);
})->with([
    'code' => ['NM', CardCondition::NEAR_MINT],
    'lowercase code' => ['lp', CardCondition::LIGHTLY_PLAYED],
    'label' => ['Moderately Played', CardCondition::MODERATELY_PLAYED],
    'cardtrader slightly played' => ['Slightly Played', CardCondition::LIGHTLY_PLAYED],
    'cardtrader played' => ['Played', CardCondition::HEAVILY_PLAYED],
    'cardtrader poor' => ['Poor', CardCondition::DAMAGED],
    'padded value' => [' near mint ', CardCondition::NEAR_MINT],
]);

test('unknown or missing conditions are not normalized', function (?string $rawCondition) {
    expect(CardCondition::fromProviderValue($rawCondition))->toBeNull();
})->with([
    'unknown grade' => ['Mint'],
    'empty string' => [''],
    'missing' => [null],
]);

test('every condition maps to a CardTrader market name and back', function () {
    foreach (CardCondition::cases() as $condition) {
        expect(CardCondition::fromProviderValue($condition->marketConditionName()))->toBe($condition);
    }
});

test('price adjustment factors decrease with the condition', function () {
    $factors = array_map(fn (CardCondition $condition) => $condition->priceAdjustmentFactor(), CardCondition::cases());

    expect($factors)->toBe([1.0, 0.9, 0.75, 0.5, 0.25]);
});

test('normalize rejects unknown conditions', function () {
    CardCondition::normalize('Mint');
})->throws(InvalidArgumentException::class);
