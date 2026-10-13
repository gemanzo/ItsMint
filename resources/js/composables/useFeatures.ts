import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps, PlanFeatures } from '@/types';

/**
 * Premium entitlements shared by HandleInertiaRequests on every page.
 *
 * IMPORTANT: in Inertia v2 `usePage().props` is a reactive object, NOT a ref.
 * Reading it through `.value` always returns undefined, which made every
 * user (including paying ones) look like a free user.
 */
export function useFeatures() {
  const page = usePage<PageProps>();

  const features = computed<PlanFeatures | null>(() => page.props.features ?? null);

  const isPremium = computed(() => features.value?.portfolio_unlimited === true);
  const canViewAnalytics = computed(() => features.value?.analytics_portfolio === true);
  const canExportCsv = computed(() => features.value?.export_csv === true);

  // Free users can only use their oldest portfolio
  const oldestPortfolioId = computed<number | null>(() => page.props.oldest_portfolio_id ?? null);

  return {
    features,
    isPremium,
    canViewAnalytics,
    canExportCsv,
    oldestPortfolioId,
  };
}
