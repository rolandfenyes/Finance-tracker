import type { Translation } from '@jsverse/transloco';

export const SUPPORTED_LANGUAGES = ['en', 'es', 'hu'] as const;

export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number];

export const DEFAULT_LANGUAGE: SupportedLanguage = 'en';

export function isSupportedLanguage(value: string): value is SupportedLanguage {
  return SUPPORTED_LANGUAGES.some((language) => language === value);
}

export const TRANSLATIONS: Readonly<Record<SupportedLanguage, Translation>> = {
  en: {
    app: {
      name: 'MyMoneyMap',
      foundation: 'Design-system foundation',
    },
    accessibility: {
      skipToContent: 'Skip to main content',
    },
    shell: {
      auth: {
        eyebrow: 'Secure access',
        title: 'Welcome to MyMoneyMap',
        description: 'Authentication experiences will be implemented in Step 03.',
      },
      onboarding: {
        eyebrow: 'Getting started',
        title: 'Set up your workspace',
        description: 'Server-directed onboarding will be implemented in Step 03.',
      },
      product: {
        eyebrow: 'Personal finance',
        title: 'Your financial workspace',
        description: 'Dashboard features will be implemented in Step 04.',
        navigationLabel: 'Primary navigation preview',
      },
      admin: {
        eyebrow: 'Operations',
        title: 'Administration workspace',
        description: 'Role-gated administration will be implemented in Step 11.',
      },
      error: {
        eyebrow: 'Page unavailable',
        title: 'We could not find this page',
        description: 'Check the address or return to the application entry point.',
      },
    },
    navigation: {
      home: 'Home',
      activity: 'Activity',
      plan: 'Plan',
      goals: 'Goals',
      more: 'More',
    },
    state: {
      loading: 'Loading',
      emptyTitle: 'Nothing to show yet',
      emptyDescription: 'Content will appear here when it becomes available.',
      errorTitle: 'Something went wrong',
      errorDescription: 'Try again when you are ready.',
      retry: 'Try again',
      continue: 'Continue',
      partial: 'Some information is temporarily unavailable.',
      disabled: 'This capability is currently unavailable.',
    },
  },
  es: {
    app: {
      name: 'MyMoneyMap',
      foundation: 'Base del sistema de diseño',
    },
    accessibility: {
      skipToContent: 'Saltar al contenido principal',
    },
    shell: {
      auth: {
        eyebrow: 'Acceso seguro',
        title: 'Te damos la bienvenida a MyMoneyMap',
        description: 'Las experiencias de autenticación se implementarán en el Paso 03.',
      },
      onboarding: {
        eyebrow: 'Primeros pasos',
        title: 'Configura tu espacio',
        description: 'La configuración guiada por el servidor se implementará en el Paso 03.',
      },
      product: {
        eyebrow: 'Finanzas personales',
        title: 'Tu espacio financiero',
        description: 'Las funciones del panel se implementarán en el Paso 04.',
        navigationLabel: 'Vista previa de la navegación principal',
      },
      admin: {
        eyebrow: 'Operaciones',
        title: 'Espacio de administración',
        description: 'La administración por roles se implementará en el Paso 11.',
      },
      error: {
        eyebrow: 'Página no disponible',
        title: 'No encontramos esta página',
        description: 'Comprueba la dirección o vuelve al inicio de la aplicación.',
      },
    },
    navigation: {
      home: 'Inicio',
      activity: 'Actividad',
      plan: 'Plan',
      goals: 'Objetivos',
      more: 'Más',
    },
    state: {
      loading: 'Cargando',
      emptyTitle: 'Todavía no hay contenido',
      emptyDescription: 'El contenido aparecerá aquí cuando esté disponible.',
      errorTitle: 'Algo ha salido mal',
      errorDescription: 'Inténtalo de nuevo cuando quieras.',
      retry: 'Reintentar',
      continue: 'Continuar',
      partial: 'Parte de la información no está disponible temporalmente.',
      disabled: 'Esta función no está disponible actualmente.',
    },
  },
  hu: {
    app: {
      name: 'MyMoneyMap',
      foundation: 'Dizájnrendszer-alapok',
    },
    accessibility: {
      skipToContent: 'Ugrás a fő tartalomhoz',
    },
    shell: {
      auth: {
        eyebrow: 'Biztonságos hozzáférés',
        title: 'Üdv a MyMoneyMapben',
        description: 'A hitelesítési folyamatok a 03. lépésben készülnek el.',
      },
      onboarding: {
        eyebrow: 'Első lépések',
        title: 'Állítsd be a munkaterületed',
        description: 'A szerver által vezérelt beállítás a 03. lépésben készül el.',
      },
      product: {
        eyebrow: 'Személyes pénzügyek',
        title: 'A pénzügyi munkaterületed',
        description: 'Az irányítópult funkciói a 04. lépésben készülnek el.',
        navigationLabel: 'Elsődleges navigáció előnézete',
      },
      admin: {
        eyebrow: 'Üzemeltetés',
        title: 'Adminisztrációs munkaterület',
        description: 'A szerepkörrel védett adminisztráció a 11. lépésben készül el.',
      },
      error: {
        eyebrow: 'Az oldal nem érhető el',
        title: 'Ez az oldal nem található',
        description: 'Ellenőrizd a címet, vagy térj vissza az alkalmazás kezdőpontjához.',
      },
    },
    navigation: {
      home: 'Kezdőlap',
      activity: 'Aktivitás',
      plan: 'Terv',
      goals: 'Célok',
      more: 'Továbbiak',
    },
    state: {
      loading: 'Betöltés',
      emptyTitle: 'Még nincs megjeleníthető tartalom',
      emptyDescription: 'A tartalom itt jelenik meg, amikor elérhetővé válik.',
      errorTitle: 'Hiba történt',
      errorDescription: 'Próbáld újra, amikor készen állsz.',
      retry: 'Újrapróbálás',
      continue: 'Folytatás',
      partial: 'Néhány információ átmenetileg nem érhető el.',
      disabled: 'Ez a funkció jelenleg nem érhető el.',
    },
  },
};
