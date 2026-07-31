import type { SupportedLanguage } from '@mymoneymap/web-shared';
import { DEFAULT_LANGUAGE, isSupportedLanguage } from '@mymoneymap/web-shared';
import type { ExactDecimal } from './exact-decimal.adapter';

export type CalendarDate = string & { readonly __calendarDate: unique symbol };
export type UtcInstant = string & { readonly __utcInstant: unique symbol };
export type OpaqueCursor = string & { readonly __opaqueCursor: unique symbol };
export type RecurrenceRule = string & { readonly __recurrenceRule: unique symbol };
export type CurrencyCode = string & { readonly __currencyCode: unique symbol };

const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const INSTANT_PATTERN =
  /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,3})?Z$/;

export function calendarDate(value: string): CalendarDate {
  const match = DATE_PATTERN.exec(value);
  if (!match) throw new Error('Calendar date must use YYYY-MM-DD');
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const candidate = new Date(Date.UTC(year, month - 1, day));
  if (
    candidate.getUTCFullYear() !== year ||
    candidate.getUTCMonth() !== month - 1 ||
    candidate.getUTCDate() !== day
  ) {
    throw new Error('Calendar date must be a valid Gregorian date');
  }
  return value as CalendarDate;
}

export function formatCalendarDate(value: CalendarDate, locale: SupportedLanguage): string {
  const [year, month, day] = value.split('-');
  if (locale === 'en') return `${month}/${day}/${year}`;
  if (locale === 'es') return `${day}/${month}/${year}`;
  return `${year}.${month}.${day}.`;
}

export function utcInstant(value: string): UtcInstant {
  if (!INSTANT_PATTERN.test(value)) throw new Error('Instant must be an ISO timestamp ending in Z');
  const parsed = Date.parse(value);
  if (!Number.isFinite(parsed)) throw new Error('Instant must be valid');
  return new Date(parsed).toISOString() as UtcInstant;
}

export function formatInstant(
  value: UtcInstant,
  locale: SupportedLanguage,
  timeZone: string,
): string {
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone,
  }).format(new Date(value));
}

export function currencyCode(value: string): CurrencyCode {
  if (!/^[A-Z]{3}$/.test(value)) throw new Error('Currency code must be an ISO-style code');
  return value as CurrencyCode;
}

export function formatMoney(
  value: ExactDecimal,
  currency: CurrencyCode,
  locale: SupportedLanguage,
): string {
  return `${formatExact(value, locale)} ${currency}`;
}

export function formatPercent(value: ExactDecimal, locale: SupportedLanguage): string {
  return `${formatExact(value, locale)}%`;
}

export function opaqueCursor(value: string): OpaqueCursor {
  if (value.trim().length === 0) throw new Error('Cursor must not be empty');
  return value as OpaqueCursor;
}

export function cursorValue(value: OpaqueCursor): string {
  return value;
}

export function recurrenceRule(value: string): RecurrenceRule {
  if (value.trim().length === 0 || /[\r\n]/.test(value)) {
    throw new Error('Recurrence rule must be one non-empty line');
  }
  return value as RecurrenceRule;
}

export function resolveLocale(value: string | null | undefined): SupportedLanguage {
  return value && isSupportedLanguage(value) ? value : DEFAULT_LANGUAGE;
}

export type Freshness =
  | { readonly state: 'current'; readonly calculatedAt: UtcInstant }
  | { readonly state: 'stale'; readonly calculatedAt: UtcInstant }
  | { readonly state: 'delayed'; readonly calculatedAt: UtcInstant | null }
  | { readonly state: 'unavailable'; readonly calculatedAt: UtcInstant | null };

function formatExact(value: ExactDecimal, locale: SupportedLanguage): string {
  const negative = value.startsWith('-');
  const unsigned = value.replace(/^[+-]/, '');
  const [integer = '0', fraction] = unsigned.split('.');
  const group = locale === 'en' ? ',' : locale === 'es' ? '.' : '\u00a0';
  const decimal = locale === 'en' ? '.' : ',';
  const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, group);
  return `${negative ? '-' : ''}${grouped}${fraction ? decimal + fraction : ''}`;
}
