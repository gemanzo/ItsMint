import {
  CARD_CONDITION_ALIASES,
  CARD_CONDITION_DEFINITIONS,
} from '@/constants/generated/cardVocabulary';

export interface CardCondition {
  value: string;
  label: string;
  description: string;
  priceAdjustment: number;
}

/**
 * Card conditions for the forms, generated from the backend CardCondition enum
 * (php artisan itsmint:export-card-vocabulary): never edit the list here.
 */
export const CARD_CONDITIONS: CardCondition[] = CARD_CONDITION_DEFINITIONS.map(
  (conditionDefinition) => ({
    value: conditionDefinition.value,
    label: `${conditionDefinition.label} (${conditionDefinition.value})`,
    description: conditionDefinition.description,
    priceAdjustment: conditionDefinition.priceAdjustment,
  })
);

export const CONDITION_PRICE_ADJUSTMENTS = CARD_CONDITIONS.reduce(
  (acc, condition) => {
    acc[condition.value] = condition.priceAdjustment;
    return acc;
  },
  {} as Record<string, number>
);

/**
 * Convert a condition written in any accepted spelling (code, label or CardTrader
 * name such as "Slightly Played") to the condition code used by the forms.
 * Unknown values fall back to Near Mint.
 */
export function toConditionCode(condition: string | null | undefined): string {
  if (!condition) {
    return 'NM';
  }

  return CARD_CONDITION_ALIASES[condition.trim().toLowerCase()] ?? 'NM';
}
