import { CARD_LANGUAGE_DEFINITIONS } from '@/constants/generated/cardVocabulary';

export interface CardLanguage {
  name: string;
  code: string;
  countryCode: string;
  flag?: string;
}

/**
 * Card languages for selectors, generated from the backend CardLanguage enum
 * (php artisan itsmint:export-card-vocabulary), plus the "Any" filter option.
 */
export const CARD_LANGUAGES: CardLanguage[] = [
  { name: 'Any', code: '', countryCode: 'un' },
  ...CARD_LANGUAGE_DEFINITIONS.map((languageDefinition) => ({
    name: languageDefinition.shortName,
    code: languageDefinition.code,
    countryCode: languageDefinition.countryCode,
  })),
];

export const AVAILABLE_LANGUAGES = CARD_LANGUAGES.filter(
  (lang) => lang.code !== ''
);
