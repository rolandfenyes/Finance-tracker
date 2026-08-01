import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { AppLanguageService, AppTranslationLoader, provideAppI18n } from './i18n.providers';
import {
  DEFAULT_LANGUAGE,
  isSupportedLanguage,
  SUPPORTED_LANGUAGES,
  TRANSLATIONS,
} from './translations';

describe('application localization', () => {
  let loader: AppTranslationLoader;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    loader = TestBed.inject(AppTranslationLoader);
  });

  it('registers exactly the approved runtime languages', () => {
    expect(SUPPORTED_LANGUAGES).toEqual(['en', 'es', 'hu']);
    expect(DEFAULT_LANGUAGE).toBe('en');
  });

  it('uses English for unsupported locales', async () => {
    await expect(firstValueFrom(loader.getTranslation('de'))).resolves.toBe(TRANSLATIONS.en);
  });

  it.each(SUPPORTED_LANGUAGES)('loads the complete %s shell catalogue', async (language) => {
    const translation = await firstValueFrom(loader.getTranslation(language));
    expect(translation).toHaveProperty('shell.auth.title');
    expect(translation).toHaveProperty('shell.onboarding.title');
    expect(translation).toHaveProperty('shell.product.title');
    expect(translation).toHaveProperty('shell.admin.title');
    expect(translation).toHaveProperty('dashboard.views.postedTitle');
    expect(translation).toHaveProperty('dashboard.conversion.unavailable.description');
    expect(translation).toHaveProperty('more.navigationLabel');
    expect(translation).toHaveProperty('routeStatus.unavailable.title');
    expect(translation).toHaveProperty('routeStatus.forbidden.title');
    expect(translation).toHaveProperty('routeStatus.notFound.title');
    expect(isSupportedLanguage(language)).toBe(true);
  });
});

describe('AppLanguageService', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideAppI18n()] });
  });

  it('updates the document language at runtime and falls back to English', () => {
    const service = TestBed.inject(AppLanguageService);
    service.setLanguage('hu');
    expect(document.documentElement.lang).toBe('hu');

    service.setLanguage('unsupported');
    expect(document.documentElement.lang).toBe('en');
  });
});
