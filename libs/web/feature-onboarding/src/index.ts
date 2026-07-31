import { inject } from '@angular/core';
import { Router, type CanMatchFn, type Routes } from '@angular/router';
import { OnboardingPolicy } from '@mymoneymap/web-core';
import {
  CategoriesStepComponent,
  CurrenciesStepComponent,
  IncomeStepComponent,
  OnboardingEntryComponent,
  OnboardingLayoutComponent,
  RulesStepComponent,
  ThemeStepComponent,
  TutorialStepComponent,
} from './lib/onboarding-pages';

const serverStepGuard: CanMatchFn = async (route) => {
  const policy = inject(OnboardingPolicy);
  const router = inject(Router);
  const state = await policy.load();
  const requested = route.path;
  return state.next === requested
    ? true
    : router.parseUrl(state.next === 'complete' ? '/app' : `/onboarding/${state.next}`);
};

export const ONBOARDING_ROUTES: Routes = [
  {
    path: '',
    component: OnboardingLayoutComponent,
    children: [
      { path: '', pathMatch: 'full', component: OnboardingEntryComponent },
      { path: 'theme', canMatch: [serverStepGuard], component: ThemeStepComponent },
      { path: 'rules', canMatch: [serverStepGuard], component: RulesStepComponent },
      { path: 'currencies', canMatch: [serverStepGuard], component: CurrenciesStepComponent },
      { path: 'categories', canMatch: [serverStepGuard], component: CategoriesStepComponent },
      { path: 'income', canMatch: [serverStepGuard], component: IncomeStepComponent },
      { path: 'tutorial', canMatch: [serverStepGuard], component: TutorialStepComponent },
    ],
  },
];
