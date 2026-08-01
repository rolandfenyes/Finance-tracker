import type { Routes } from '@angular/router';
import {
  MonthReportPageComponent,
  ReportYearsPageComponent,
  YearReportPageComponent,
} from './lib/report-pages';
import { ReportsFacade } from './lib/reports.facade';
export { ReportsFacade } from './lib/reports.facade';
export const REPORT_ROUTES: Routes = [
  {
    path: '',
    providers: [ReportsFacade],
    children: [
      { path: '', component: ReportYearsPageComponent },
      { path: ':year', component: YearReportPageComponent },
      { path: ':year/:month', component: MonthReportPageComponent },
    ],
  },
];
