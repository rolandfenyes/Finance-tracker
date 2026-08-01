import type { Routes } from '@angular/router';
import {
  BudgetPageComponent,
  CategoriesPageComponent,
  IncomePageComponent,
  PlanningHubPageComponent,
  ScheduleEditorPageComponent,
  SchedulesPageComponent,
} from './lib/planning-pages';
import { PlanningFacade } from './lib/planning.facade';

export { PlanningFacade } from './lib/planning.facade';
export { CategoriesPageComponent, IncomePageComponent } from './lib/planning-pages';
export { buildSupportedRRule, describeSupportedRRule } from './lib/rrule';

export const PLANNING_ROUTES: Routes = [
  {
    path: '',
    providers: [PlanningFacade],
    children: [
      { path: '', component: PlanningHubPageComponent },
      { path: 'budget', component: BudgetPageComponent },
      { path: 'categories', component: CategoriesPageComponent },
      { path: 'income', component: IncomePageComponent },
      { path: 'schedules', component: SchedulesPageComponent },
      { path: 'schedules/new', component: ScheduleEditorPageComponent },
      { path: 'schedules/:id/edit', component: ScheduleEditorPageComponent },
    ],
  },
];
