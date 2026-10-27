<?php

use App\Enums\CardLanguage;

test('provider spellings are normalized to the canonical language', function (string $rawLanguage, CardLanguage $expectedLanguage) {
    expect(CardLanguage::fromProviderValue($rawLanguage))->toBe($expectedLanguage);
})->with([
    'english' => ['en', CardLanguage::ENGLISH],
    'uppercase english' => ['EN', CardLanguage::ENGLISH],
    'japanese iso code' => ['ja', CardLanguage::JAPANESE],
    'japanese cardtrader code' => ['jp', CardLanguage::JAPANESE],
    'simplified chinese uppercase' => ['zh-CN', CardLanguage::CHINESE_SIMPLIFIED],
    'korean country code' => ['kr', CardLanguage::KOREAN],
    'padded value' => [' fr ', CardLanguage::FRENCH],
]);

test('unknown or missing languages are not normalized', function (?string $rawLanguage) {
    expect(CardLanguage::fromProviderValue($rawLanguage))->toBeNull();
})->with([
    'placeholder used by the old code' => ['unknown'],
    'empty string' => [''],
    'missing' => [null],
    'unsupported language' => ['de'],
]);

test('every canonical code fits the card_prices.language column', function () {
    foreach (CardLanguage::cases() as $language) {
        expect(strlen($language->value))->toBeLessThanOrEqual(5);
    }
});
