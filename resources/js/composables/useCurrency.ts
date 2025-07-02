export function useCurrency() {
  const formatCurrency = (
    value: number | null | undefined,
    currency = 'EUR',
    locale = 'it-IT'
  ): string => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
    }).format(value);
  };

  const formatPrice = (priceCents: number | null | undefined): string => {
    if (!priceCents) return 'N/A';
    return formatCurrency(priceCents / 100);
  };

  const priceToString = (price: number | null): string => {
    return price ? price.toFixed(2) : '';
  };

  const stringToPrice = (str: string): number | null => {
    const parsed = parseFloat(str);
    return isNaN(parsed) ? null : parsed;
  };

  return {
    formatCurrency,
    formatPrice,
    priceToString,
    stringToPrice,
  };
}
