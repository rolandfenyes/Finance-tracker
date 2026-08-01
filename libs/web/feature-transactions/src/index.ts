import type { Routes } from '@angular/router';
import {
  ActivityPageComponent,
  JournalCommandPageComponent,
  JournalDetailPageComponent,
  JournalReversePageComponent,
} from './lib/journal-pages';

export { JournalFacade } from './lib/journal.facade';
export const JOURNAL_ROUTES: Routes = [
  { path: '', component: ActivityPageComponent },
  { path: 'new', component: JournalCommandPageComponent },
  { path: ':id', component: JournalDetailPageComponent },
  { path: ':id/correct', component: JournalCommandPageComponent, data: { mode: 'correct' } },
  { path: ':id/reverse', component: JournalReversePageComponent },
];
