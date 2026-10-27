export interface CardCondition {
  value: string;
  label: string;
  description: string;
  priceAdjustment: number;
}

export const CARD_CONDITIONS: CardCondition[] = [
  {
    value: 'NM',
    label: 'Near Mint (NM)',
    description: 'Perfect condition',
    priceAdjustment: 1.0,
  },
  {
    value: 'LP',
    label: 'Lightly Played (LP)',
    description: 'Minor wear',
    priceAdjustment: 0.9,
  },
  {
    value: 'MP',
    label: 'Moderately Played (MP)',
    description: 'Visible wear',
    priceAdjustment: 0.75,
  },
  {
    value: 'HP',
    label: 'Heavily Played (HP)',
    description: 'Major wear',
    priceAdjustment: 0.5,
  },
  {
    value: 'DMG',
    label: 'Damaged (DMG)',
    description: 'Significant damage',
    priceAdjustment: 0.25,
  },
];

export const CONDITION_PRICE_ADJUSTMENTS = CARD_CONDITIONS.reduce(
  (acc, condition) => {
    acc[condition.value] = condition.priceAdjustment;
    return acc;
  },
  {} as Record<string, number>
);

/**
 * Full condition names (as stored in card prices, e.g. "Near Mint") mapped to the
 * condition codes used by the forms. Mirrors UserCard::$conditionAbbreviations.
 */
const CONDITION_CODES_BY_FULL_NAME: Record<string, string> = {
  'Near Mint': 'NM',
  'Slightly Played': 'LP',
  'Lightly Played': 'LP',
  'Moderately Played': 'MP',
  Played: 'HP',
  'Heavily Played': 'HP',
  Poor: 'DMG',
  Damaged: 'DMG',
};

/**
 * Convert a condition (full name or code) to the condition code used by the forms.
 * Unknown values fall back to Near Mint.
 */
export function toConditionCode(condition: string | null | undefined): string {
  if (!condition) {
    return 'NM';
  }

  const isAlreadyACode = CARD_CONDITIONS.some(
    (cardCondition) => cardCondition.value === condition
  );
  if (isAlreadyACode) {
    return condition;
  }

  return CONDITION_CODES_BY_FULL_NAME[condition] ?? 'NM';
}
