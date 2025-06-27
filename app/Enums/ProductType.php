<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case SINGLE_CARD = 'single_card';
    case DON_TOKEN = 'don_token';
    case BOOSTER_PACK = 'booster_pack';
    case BOOSTER_BOX = 'booster_box';
    case STARTER_DECK = 'starter_deck';
    case SLEEVES = 'sleeves';
    case PLAYMAT = 'playmat';
    case STORAGE_BOX = 'storage_box';
    case BINDER = 'binder';
    case BUNDLE_SET = 'bundle_set';
    case UNKNOWN = 'unknown';

    /**
     * Get product type from ct_category_id.
     */
    public static function fromCategoryId(?int $categoryId): self
    {
        return match ($categoryId) {
            192 => self::SINGLE_CARD,
            255 => self::DON_TOKEN,
            194 => self::BOOSTER_PACK,
            193 => self::BOOSTER_BOX,
            195 => self::STARTER_DECK,
            197 => self::SLEEVES,
            196 => self::PLAYMAT,
            198 => self::STORAGE_BOX,
            199 => self::BINDER,
            200, 201, 253, 256, 257 => self::BUNDLE_SET, // Various sets and special products
            default => self::UNKNOWN,
        };
    }

    /**
     * Check if product type is a single card that should be matched.
     */
    public function isSingleCard(): bool
    {
        return $this === self::SINGLE_CARD;
    }

    /**
     * Check if product type is a card-like item (including DON tokens).
     */
    public function isCardLike(): bool
    {
        return in_array($this, [self::SINGLE_CARD, self::DON_TOKEN]);
    }

    /**
     * Check if product type is an accessory.
     */
    public function isAccessory(): bool
    {
        return in_array($this, [
            self::SLEEVES,
            self::PLAYMAT,
            self::STORAGE_BOX,
            self::BINDER,
        ]);
    }

    /**
     * Check if product type is a pack/box product.
     */
    public function isPackProduct(): bool
    {
        return in_array($this, [
            self::BOOSTER_PACK,
            self::BOOSTER_BOX,
            self::STARTER_DECK,
            self::BUNDLE_SET,
        ]);
    }

    /**
     * Get human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SINGLE_CARD => 'Single Card',
            self::DON_TOKEN => 'DON!! Token',
            self::BOOSTER_PACK => 'Booster Pack',
            self::BOOSTER_BOX => 'Booster Box',
            self::STARTER_DECK => 'Starter Deck',
            self::SLEEVES => 'Card Sleeves',
            self::PLAYMAT => 'Playmat',
            self::STORAGE_BOX => 'Storage Box',
            self::BINDER => 'Binder',
            self::BUNDLE_SET => 'Bundle/Set',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * Get all category IDs for this product type.
     */
    public function getCategoryIds(): array
    {
        return match ($this) {
            self::SINGLE_CARD => [192],
            self::DON_TOKEN => [255],
            self::BOOSTER_PACK => [194],
            self::BOOSTER_BOX => [193],
            self::STARTER_DECK => [195],
            self::SLEEVES => [197],
            self::PLAYMAT => [196],
            self::STORAGE_BOX => [198],
            self::BINDER => [199],
            self::BUNDLE_SET => [200, 201, 253, 256, 257],
            self::UNKNOWN => [],
        };
    }

    /**
     * Get icon for UI display.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::SINGLE_CARD => '🃏',
            self::DON_TOKEN => '⚡',
            self::BOOSTER_PACK => '📦',
            self::BOOSTER_BOX => '📦',
            self::STARTER_DECK => '🎮',
            self::SLEEVES => '🛡️',
            self::PLAYMAT => '🏟️',
            self::STORAGE_BOX => '📦',
            self::BINDER => '📁',
            self::BUNDLE_SET => '🎁',
            self::UNKNOWN => '❓',
        };
    }

    /**
     * Get category IDs for single cards and DON!! tokens.
     */
    public static function getSingleCardCategoryIds(): array
    {
        return [192, 255]; // Single cards and DON!! tokens
    }

    /**
     * Get all available product types as array.
     */
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get product types that are considered cards.
     */
    public static function getCardTypes(): array
    {
        return [
            self::SINGLE_CARD->value,
            self::DON_TOKEN->value,
        ];
    }

    /**
     * Get product types that are considered accessories/products.
     */
    public static function getProductTypes(): array
    {
        return array_diff(self::getValues(), self::getCardTypes());
    }
}
