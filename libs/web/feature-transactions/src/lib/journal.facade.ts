import { Injectable, inject, signal } from '@angular/core';
import type { CorrectJournalEntryDto } from '@mymoneymap/generated-api-client/models/correct-journal-entry-dto';
import type { CreateJournalEntryDto } from '@mymoneymap/generated-api-client/models/create-journal-entry-dto';
import type { JournalCorrectionResponseDto } from '@mymoneymap/generated-api-client/models/journal-correction-response-dto';
import type { JournalEntryResponseDto } from '@mymoneymap/generated-api-client/models/journal-entry-response-dto';
import type { ReverseJournalEntryDto } from '@mymoneymap/generated-api-client/models/reverse-journal-entry-dto';
import { LedgerService } from '@mymoneymap/generated-api-client/services/ledger.service';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import {
  BrowserIdempotencyKeyFactory,
  CommandLifecycle,
  IDEMPOTENT_OPERATIONS,
  idempotencyContext,
  parseApiError,
} from '@mymoneymap/web-core';
import { firstValueFrom } from 'rxjs';

export interface JournalQuery {
  readonly dateFrom?: string;
  readonly dateTo?: string;
  readonly cursor?: string;
}
export interface JournalState {
  readonly status: 'idle' | 'loading' | 'ready' | 'empty' | 'error';
  readonly items: readonly JournalEntryResponseDto[];
  readonly nextCursor: string | null;
  readonly requestId: string | null;
}

@Injectable({ providedIn: 'root' })
export class JournalFacade {
  private readonly api = inject(LedgerService);
  private readonly reporting = inject(ReportingService);
  private readonly keys = inject(BrowserIdempotencyKeyFactory);
  private readonly entryCache = new Map<string, JournalEntryResponseDto>();
  private readonly stateSignal = signal<JournalState>({
    status: 'idle',
    items: [],
    nextCursor: null,
    requestId: null,
  });
  private lastQuery: JournalQuery = {};

  readonly state = this.stateSignal.asReadonly();
  readonly createCommand = new CommandLifecycle<JournalEntryResponseDto>(this.keys);
  readonly correctCommand = new CommandLifecycle<JournalCorrectionResponseDto>(this.keys);
  readonly reverseCommand = new CommandLifecycle<JournalEntryResponseDto>(this.keys);

  entry(id: string): JournalEntryResponseDto | null {
    return (
      this.entryCache.get(id) ?? this.stateSignal().items.find((item) => item.id === id) ?? null
    );
  }

  async load(query: JournalQuery = {}, append = false): Promise<void> {
    this.lastQuery = { dateFrom: query.dateFrom, dateTo: query.dateTo };
    this.stateSignal.update((state) => ({ ...state, status: 'loading', requestId: null }));
    try {
      const page = await firstValueFrom(this.api.ledgerControllerList({ ...query, limit: 25 }));
      const items = append ? [...this.stateSignal().items, ...page.items] : page.items;
      for (const item of items) this.entryCache.set(item.id, item);
      this.stateSignal.set({
        status: items.length === 0 ? 'empty' : 'ready',
        items,
        nextCursor: page.nextCursor,
        requestId: null,
      });
    } catch (error) {
      const parsed = parseApiError(error);
      this.stateSignal.update((state) => ({
        ...state,
        status: 'error',
        requestId: parsed.requestId,
      }));
    }
  }

  async ensureEntry(id: string): Promise<void> {
    if (!this.entry(id)) await this.load();
  }

  async create(body: CreateJournalEntryDto): Promise<JournalEntryResponseDto | null> {
    const intent = intentId('create', body);
    const key = this.createCommand.begin(intent);
    try {
      const result = await firstValueFrom(
        this.api.ledgerControllerCreate(
          { 'Idempotency-Key': key, body },
          idempotencyContext(IDEMPOTENT_OPERATIONS.journalCreate, key),
        ),
      );
      this.createCommand.succeed(result);
      this.entryCache.set(result.id, result);
      await this.load(this.lastQuery);
      await this.refreshReports(body.postedOn);
      return result;
    } catch (error) {
      await this.handleFailure(this.createCommand, error);
      return null;
    }
  }

  async correct(
    id: string,
    body: CorrectJournalEntryDto,
  ): Promise<JournalCorrectionResponseDto | null> {
    const intent = intentId(`correct:${id}`, body);
    const key = this.correctCommand.begin(intent);
    try {
      const result = await firstValueFrom(
        this.api.ledgerControllerCorrect(
          { id, 'Idempotency-Key': key, body },
          idempotencyContext(IDEMPOTENT_OPERATIONS.journalCorrection, key),
        ),
      );
      this.correctCommand.succeed(result);
      this.entryCache.set(result.reversal.id, result.reversal);
      this.entryCache.set(result.replacement.id, result.replacement);
      await this.load(this.lastQuery);
      await this.refreshReports(body.postedOn);
      return result;
    } catch (error) {
      await this.handleFailure(this.correctCommand, error);
      return null;
    }
  }

  async reverse(id: string, body: ReverseJournalEntryDto): Promise<JournalEntryResponseDto | null> {
    const intent = intentId(`reverse:${id}`, body);
    const key = this.reverseCommand.begin(intent);
    try {
      const result = await firstValueFrom(
        this.api.ledgerControllerReverse(
          { id, 'Idempotency-Key': key, body },
          idempotencyContext(IDEMPOTENT_OPERATIONS.journalReversal, key),
        ),
      );
      this.reverseCommand.succeed(result);
      this.entryCache.set(result.id, result);
      await this.load(this.lastQuery);
      await this.refreshReports(body.postedOn);
      return result;
    } catch (error) {
      await this.handleFailure(this.reverseCommand, error);
      return null;
    }
  }

  private async handleFailure<T>(command: CommandLifecycle<T>, error: unknown): Promise<void> {
    const parsed = parseApiError(error);
    if (parsed.kind === 'unavailable' || parsed.code === 'IDEMPOTENCY_IN_PROGRESS') {
      command.uncertain(parsed);
      await this.load(this.lastQuery);
    } else command.fail(parsed);
  }

  private async refreshReports(postedOn: string): Promise<void> {
    const [year, month] = postedOn.split('-').map((part) => Number(part));
    if (!year || !month) return;
    await Promise.allSettled([
      firstValueFrom(this.reporting.reportingControllerMonth({ year, month, limit: 5 })),
      firstValueFrom(this.reporting.reportingControllerCurrent({ limit: 5 })),
    ]);
  }
}

function intentId(operation: string, body: object): string {
  return `${operation}:${JSON.stringify(body)}`;
}
