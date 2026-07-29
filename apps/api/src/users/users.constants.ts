export const supportedLocales = ['en', 'hu', 'es'] as const;
export type SupportedLocale = (typeof supportedLocales)[number];

export const supportedThemes = [
  'polar-quartz',
  'verdant-horizon',
  'celestial-tide',
  'blush-nocturne',
  'ember-vanguard',
  'lilac-eclipse',
  'solaris-bloom',
  'dune-mirage',
] as const;
export type SupportedTheme = (typeof supportedThemes)[number];

export const defaultLocale: SupportedLocale = 'en';
export const defaultTheme: SupportedTheme = 'verdant-horizon';

export const onboardingSteps = {
  notStarted: 0,
  theme: 1,
  rules: 2,
  currencies: 3,
  categories: 4,
  income: 5,
  done: 6,
} as const;

export type OnboardingStep = (typeof onboardingSteps)[keyof typeof onboardingSteps];
export type OnboardingDestination =
  'theme' | 'rules' | 'currencies' | 'categories' | 'income' | 'tutorial' | 'complete';
