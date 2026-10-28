// GENERATED FILE - DO NOT EDIT.
// Source: App\Enums\CardCondition and App\Enums\CardLanguage.
// Regenerate with: php artisan itsmint:export-card-vocabulary

// Canonical card conditions (codes stored for user cards)
export const CARD_CONDITION_DEFINITIONS = [
    {
        "value": "NM",
        "label": "Near Mint",
        "description": "Perfect condition",
        "marketConditionName": "Near Mint",
        "priceAdjustment": 1.0
    },
    {
        "value": "LP",
        "label": "Lightly Played",
        "description": "Minor wear",
        "marketConditionName": "Slightly Played",
        "priceAdjustment": 0.9
    },
    {
        "value": "MP",
        "label": "Moderately Played",
        "description": "Visible wear",
        "marketConditionName": "Moderately Played",
        "priceAdjustment": 0.75
    },
    {
        "value": "HP",
        "label": "Heavily Played",
        "description": "Major wear",
        "marketConditionName": "Played",
        "priceAdjustment": 0.5
    },
    {
        "value": "DMG",
        "label": "Damaged",
        "description": "Significant damage",
        "marketConditionName": "Poor",
        "priceAdjustment": 0.25
    }
] as const;

// Accepted spellings (lowercase) mapped to the canonical condition code
export const CARD_CONDITION_ALIASES: Record<string, string> = {
    "nm": "NM",
    "near mint": "NM",
    "lp": "LP",
    "lightly played": "LP",
    "sp": "LP",
    "slightly played": "LP",
    "mp": "MP",
    "moderately played": "MP",
    "hp": "HP",
    "heavily played": "HP",
    "pl": "HP",
    "played": "HP",
    "dmg": "DMG",
    "damaged": "DMG",
    "po": "DMG",
    "poor": "DMG"
};

// Supported card languages
export const CARD_LANGUAGE_DEFINITIONS = [
    {
        "code": "en",
        "name": "English",
        "shortName": "EN",
        "countryCode": "us"
    },
    {
        "code": "jp",
        "name": "Japanese",
        "shortName": "JP",
        "countryCode": "jp"
    },
    {
        "code": "fr",
        "name": "French",
        "shortName": "FR",
        "countryCode": "fr"
    },
    {
        "code": "zh-cn",
        "name": "Chinese Simplified",
        "shortName": "CHS",
        "countryCode": "cn"
    },
    {
        "code": "zh-tw",
        "name": "Chinese Traditional",
        "shortName": "CHT",
        "countryCode": "tw"
    },
    {
        "code": "ko",
        "name": "Korean",
        "shortName": "KO",
        "countryCode": "kr"
    }
] as const;
