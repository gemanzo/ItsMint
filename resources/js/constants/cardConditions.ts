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
