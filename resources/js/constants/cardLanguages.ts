export interface CardLanguage {
  name: string;
  code: string;
  countryCode: string;
  flag?: string;
}

export const CARD_LANGUAGES: CardLanguage[] = [
  { name: 'Any', code: '', countryCode: 'un' },
  { name: 'EN', code: 'en', countryCode: 'us' },
  { name: 'JP', code: 'jp', countryCode: 'jp' },
  { name: 'FR', code: 'fr', countryCode: 'fr' },
  { name: 'CHI', code: 'zh-cn', countryCode: 'cn' },
];

export const AVAILABLE_LANGUAGES = CARD_LANGUAGES.filter(
  (lang) => lang.code !== ''
);
