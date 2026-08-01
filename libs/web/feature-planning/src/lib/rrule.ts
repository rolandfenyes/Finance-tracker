export type SupportedFrequency = 'ONCE' | 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY';

export interface SupportedRRuleInput {
  readonly frequency: SupportedFrequency;
  readonly interval: string;
  readonly byDay?: readonly string[];
  readonly byMonthDay?: string;
  readonly byMonth?: string;
  readonly count?: string;
  readonly until?: string;
}

/** Builds only the documented subset; the API remains authoritative for validation. */
export function buildSupportedRRule(input: SupportedRRuleInput): string {
  if (input.frequency === 'ONCE') return '';
  const parts = [`FREQ=${input.frequency}`, `INTERVAL=${input.interval}`];
  if (input.frequency === 'WEEKLY' && input.byDay?.length) {
    parts.push(`BYDAY=${input.byDay.join(',')}`);
  }
  if ((input.frequency === 'MONTHLY' || input.frequency === 'YEARLY') && input.byMonthDay) {
    parts.push(`BYMONTHDAY=${input.byMonthDay}`);
  }
  if (input.frequency === 'YEARLY' && input.byMonth) parts.push(`BYMONTH=${input.byMonth}`);
  if (input.count) parts.push(`COUNT=${input.count}`);
  if (input.until) parts.push(`UNTIL=${input.until}`);
  return parts.join(';');
}

/** Presents the server-returned canonical rule without calculating occurrences. */
export function describeSupportedRRule(value: string): string {
  if (value === '') return 'ONCE';
  return value.split(';').join(' · ');
}
