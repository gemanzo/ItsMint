<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical card languages used across the app (prices, user cards, filters).
 *
 * The backing values are the codes stored in the database: they must fit the
 * card_prices.language column (varchar 5).
 */
enum CardLanguage: string
{
    case ENGLISH = 'en';
    case JAPANESE = 'jp';
    case FRENCH = 'fr';
    case CHINESE_SIMPLIFIED = 'zh-cn';
    case CHINESE_TRADITIONAL = 'zh-tw';
    case KOREAN = 'ko';

    /**
     * Spellings used by external providers (CardTrader, Cardmarket, user input)
     * mapped to the canonical language. Keys are lowercase.
     */
    private const PROVIDER_ALIASES = [
        'en' => self::ENGLISH,
        'eng' => self::ENGLISH,
        'english' => self::ENGLISH,

        'jp' => self::JAPANESE,
        'ja' => self::JAPANESE,
        'jpn' => self::JAPANESE,
        'japanese' => self::JAPANESE,

        'fr' => self::FRENCH,
        'fra' => self::FRENCH,
        'french' => self::FRENCH,

        'zh-cn' => self::CHINESE_SIMPLIFIED,
        'zh_cn' => self::CHINESE_SIMPLIFIED,
        'zh-hans' => self::CHINESE_SIMPLIFIED,
        'cn' => self::CHINESE_SIMPLIFIED,
        'chinese simplified' => self::CHINESE_SIMPLIFIED,

        'zh-tw' => self::CHINESE_TRADITIONAL,
        'zh_tw' => self::CHINESE_TRADITIONAL,
        'zh-hant' => self::CHINESE_TRADITIONAL,
        'tw' => self::CHINESE_TRADITIONAL,
        'chinese traditional' => self::CHINESE_TRADITIONAL,

        'ko' => self::KOREAN,
        'kr' => self::KOREAN,
        'kor' => self::KOREAN,
        'korean' => self::KOREAN,
    ];

    /**
     * Normalize a language as written by an external provider.
     *
     * Returns null for missing or unknown values: they must be skipped, never stored
     * as-is (e.g. "unknown" does not even fit the database column).
     */
    public static function fromProviderValue(?string $rawLanguage): ?self
    {
        if ($rawLanguage === null) {
            return null;
        }

        $normalizedKey = strtolower(trim($rawLanguage));

        return self::PROVIDER_ALIASES[$normalizedKey] ?? null;
    }

    /**
     * Language codes mapped to their display names, e.g. ['en' => 'English', ...].
     *
     * @return array<string, string>
     */
    public static function displayNamesByCode(): array
    {
        $displayNamesByCode = [];
        foreach (self::cases() as $language) {
            $displayNamesByCode[$language->value] = $language->displayName();
        }

        return $displayNamesByCode;
    }

    /**
     * Get human-readable name.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::ENGLISH => 'English',
            self::JAPANESE => 'Japanese',
            self::FRENCH => 'French',
            self::CHINESE_SIMPLIFIED => 'Chinese Simplified',
            self::CHINESE_TRADITIONAL => 'Chinese Traditional',
            self::KOREAN => 'Korean',
        };
    }
}
