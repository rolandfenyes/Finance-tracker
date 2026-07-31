import { DOCUMENT } from '@angular/common';
import {
  DestroyRef,
  Injectable,
  inject,
  isDevMode,
  makeEnvironmentProviders,
  provideEnvironmentInitializer,
  type EnvironmentProviders,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  provideTransloco,
  type Translation,
  type TranslocoLoader,
  TranslocoService,
} from '@jsverse/transloco';
import { of, type Observable } from 'rxjs';
import {
  DEFAULT_LANGUAGE,
  isSupportedLanguage,
  SUPPORTED_LANGUAGES,
  TRANSLATIONS,
} from './translations';

@Injectable({ providedIn: 'root' })
export class AppTranslationLoader implements TranslocoLoader {
  getTranslation(language: string): Observable<Translation> {
    const resolvedLanguage = isSupportedLanguage(language) ? language : DEFAULT_LANGUAGE;
    return of(TRANSLATIONS[resolvedLanguage]);
  }
}

@Injectable({ providedIn: 'root' })
export class AppLanguageService {
  private readonly document = inject(DOCUMENT);
  private readonly destroyRef = inject(DestroyRef);
  private readonly transloco = inject(TranslocoService);

  constructor() {
    this.transloco.langChanges$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((language) => this.applyDocumentLanguage(language));
    this.applyDocumentLanguage(this.transloco.getActiveLang());
  }

  setLanguage(language: string): void {
    this.transloco.setActiveLang(isSupportedLanguage(language) ? language : DEFAULT_LANGUAGE);
  }

  private applyDocumentLanguage(language: string): void {
    this.document.documentElement.lang = isSupportedLanguage(language)
      ? language
      : DEFAULT_LANGUAGE;
  }
}

export function provideAppI18n(): EnvironmentProviders {
  return makeEnvironmentProviders([
    provideTransloco({
      config: {
        availableLangs: [...SUPPORTED_LANGUAGES],
        defaultLang: DEFAULT_LANGUAGE,
        fallbackLang: DEFAULT_LANGUAGE,
        reRenderOnLangChange: true,
        prodMode: !isDevMode(),
        missingHandler: {
          allowEmpty: false,
          logMissingKey: isDevMode(),
          useFallbackTranslation: true,
        },
      },
      loader: AppTranslationLoader,
    }),
    provideEnvironmentInitializer(() => inject(AppLanguageService)),
  ]);
}
