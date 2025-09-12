import { computed, type Ref, type ComputedRef } from 'vue';
import { CONDITION_PRICE_ADJUSTMENTS } from '@/constants/cardConditions';
import type { Blueprint } from '@/stores/portfolioStore';

export function useCardPricing(
  blueprint: Ref<Blueprint | null> | ComputedRef<Blueprint | null>,
  condition: Ref<string> | ComputedRef<string>,
  language?: Ref<string> | ComputedRef<string>
) {
  const basePriceCents = computed(() => {
    // If the caller provides current language and the blueprint carries
    // language on latest_price, ensure mismatch doesn't leak wrong base price.
    const bp = blueprint.value;
    if (!bp?.latest_price) return null;
    if (language && bp.latest_price.language && language.value) {
      const sameLang = bp.latest_price.language.toUpperCase() === language.value.toUpperCase();
      if (!sameLang) return null; // force caller to fetch language-specific blueprint price
    }
    return bp.latest_price.price_cents || null;
  });

  const conditionAdjustment = computed(() => {
    return CONDITION_PRICE_ADJUSTMENTS[condition.value] ?? 1.0;
  });

  const estimatedPriceCents = computed(() => {
    if (!basePriceCents.value) return null;
    return Math.round(basePriceCents.value * conditionAdjustment.value);
  });

  const estimatedPrice = computed(() => {
    if (!estimatedPriceCents.value) return null;
    return estimatedPriceCents.value / 100;
  });

  const calculateEstimatedPrice = (
    blueprintPrice: number,
    cardCondition: string
  ): number => {
    const adjustment = CONDITION_PRICE_ADJUSTMENTS[cardCondition] ?? 1.0;
    return Math.round(blueprintPrice * adjustment) / 100;
  };

  return {
    basePriceCents,
    conditionAdjustment,
    estimatedPriceCents,
    estimatedPrice,
    calculateEstimatedPrice,
  };
}
