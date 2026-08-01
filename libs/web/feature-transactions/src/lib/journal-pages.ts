import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators,
  type AbstractControl,
  type ValidationErrors,
} from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslocoPipe, TranslocoService } from '@jsverse/transloco';
import type { CreateJournalEntryDto } from '@mymoneymap/generated-api-client/models/create-journal-entry-dto';
import type { JournalEntryResponseDto } from '@mymoneymap/generated-api-client/models/journal-entry-response-dto';
import {
  AccountingLegDisclosureComponent,
  CorrectionComparisonComponent,
  CursorPagerComponent,
  DataViewComponent,
  type DataViewState,
  FinancialCommandReviewDialogComponent,
  type FinancialCommandReviewData,
  InlineAlertComponent,
  JournalEntryCardComponent,
  PageHeaderComponent,
} from '@mymoneymap/web-design-system';
import {
  calendarDate,
  currencyCode,
  ExactDecimalAdapter,
  formatCalendarDate,
  formatMoney,
  resolveLocale,
} from '@mymoneymap/web-core';
import { firstValueFrom } from 'rxjs';
import type { SupportedLanguage } from '@mymoneymap/web-shared';
import { JournalFacade, type JournalQuery } from './journal.facade';

const ECONOMIC_TYPES: readonly CreateJournalEntryDto['economicType'][] = [
  'external_income',
  'external_expense',
  'internal_transfer',
  'adjustment',
  'fee',
  'interest',
  'dividend',
];
const exactPositive = /^(?:0*[1-9]\d*)(?:\.\d+)?$|^0*\.\d*[1-9]\d*$/;
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CursorPagerComponent,
    DataViewComponent,
    MatFormFieldModule,
    MatInputModule,
    PageHeaderComponent,
    ReactiveFormsModule,
    RouterLink,
    TranslocoPipe,
    JournalEntryCardComponent,
  ],
  selector: 'mmm-activity-page',
  template: `
    <mmm-page-header
      [eyebrow]="'journal.eyebrow' | transloco"
      [title]="'journal.title' | transloco"
      [description]="'journal.description' | transloco"
    >
      <a class="action-button" routerLink="new">{{ 'journal.new' | transloco }}</a>
    </mmm-page-header>
    <form class="filter-panel" [formGroup]="filters" (ngSubmit)="applyFilters()">
      <mat-form-field
        ><mat-label>{{ 'journal.filters.from' | transloco }}</mat-label
        ><input matInput type="date" formControlName="dateFrom"
      /></mat-form-field>
      <mat-form-field
        ><mat-label>{{ 'journal.filters.to' | transloco }}</mat-label
        ><input matInput type="date" formControlName="dateTo"
      /></mat-form-field>
      <button class="secondary-button" type="submit">
        {{ 'journal.filters.apply' | transloco }}
      </button>
    </form>
    <mmm-data-view
      [state]="viewState()"
      [loadingTitle]="'journal.states.loading' | transloco"
      [loadingDescription]="'journal.states.loadingDescription' | transloco"
      [emptyTitle]="'journal.states.empty' | transloco"
      [emptyDescription]="'journal.states.emptyDescription' | transloco"
      [errorTitle]="'state.errorTitle' | transloco"
      [errorDescription]="'state.errorDescription' | transloco"
      [gatedTitle]="'state.disabled' | transloco"
      [gatedDescription]="'state.disabled' | transloco"
      [retryLabel]="'state.retry' | transloco"
      [requestIdLabel]="'dashboard.states.requestId' | transloco"
      [requestId]="facade.state().requestId"
      (retry)="reload()"
    >
      <section class="journal-list" [attr.aria-label]="'journal.title' | transloco">
        @for (entry of facade.state().items; track entry.id) {
          <a
            class="journal-row"
            [routerLink]="entry.id"
            [attr.aria-label]="typeLabel(entry.economicType) + ', ' + originalAmount(entry)"
            ><mmm-journal-entry-card
              [title]="typeLabel(entry.economicType)"
              [metadata]="formatDate(entry.postedOn) + ' · ' + entry.source.module"
              [amount]="originalAmount(entry)"
              [status]="conversionLabel(entry)"
          /></a>
        }
      </section>
      <mmm-cursor-pager
        [nextCursor]="facade.state().nextCursor"
        [loading]="facade.state().status === 'loading'"
        [loadMoreLabel]="'journal.loadMore' | transloco"
        [loadingLabel]="'state.loading' | transloco"
        (loadMore)="loadMore()"
      />
    </mmm-data-view>
  `,
  styles: [
    `
      .filter-panel {
        display: grid;
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-lg);
        background: var(--surface-raised);
      }
      .journal-list {
        display: grid;
        gap: 0.75rem;
      }
      .journal-row {
        display: block;
        padding: 1rem;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-md);
        color: inherit;
        text-decoration: none;
        background: var(--surface-raised);
      }
      @media (min-width: 48rem) {
        .filter-panel {
          grid-template-columns: 1fr 1fr auto;
          align-items: center;
        }
      }
    `,
  ],
})
export class ActivityPageComponent implements OnInit {
  protected readonly facade = inject(JournalFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder).nonNullable;
  private readonly decimals = inject(ExactDecimalAdapter);
  private readonly transloco = inject(TranslocoService);
  protected readonly filters = this.fb.group({ dateFrom: '', dateTo: '' });
  protected readonly viewState = computed<DataViewState>(() =>
    toDataViewState(this.facade.state().status),
  );

  ngOnInit(): void {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const query = {
        dateFrom: params.get('dateFrom') ?? undefined,
        dateTo: params.get('dateTo') ?? undefined,
        cursor: params.get('cursor') ?? undefined,
      };
      this.filters.patchValue({ dateFrom: query.dateFrom ?? '', dateTo: query.dateTo ?? '' });
      void this.facade.load(query);
    });
  }
  protected applyFilters(): void {
    const value = this.filters.getRawValue();
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { dateFrom: value.dateFrom || null, dateTo: value.dateTo || null, cursor: null },
    });
  }
  protected reload(): void {
    void this.facade.load(this.query());
  }
  protected loadMore(): void {
    const cursor = this.facade.state().nextCursor;
    if (cursor) void this.facade.load({ ...this.query(), cursor }, true);
  }
  protected typeLabel(type: string): string {
    return this.transloco.translate(`journal.types.${type}`);
  }
  protected formatDate(value: string): string {
    return formatCalendarDate(calendarDate(value), this.locale());
  }
  protected originalAmount(entry: JournalEntryResponseDto): string {
    const leg = entry.legs[0];
    return leg
      ? formatMoney(this.decimals.parse(leg.amount), currencyCode(leg.currency), this.locale())
      : this.transloco.translate('dashboard.unavailable');
  }
  protected conversionLabel(entry: JournalEntryResponseDto): string {
    const conversion = entry.conversion;
    if (!conversion || conversion.status === 'unavailable' || !conversion.convertedAmount)
      return this.transloco.translate('dashboard.unavailable');
    return `${formatMoney(this.decimals.parse(conversion.convertedAmount), currencyCode(conversion.targetCurrency), this.locale())} · ${this.transloco.translate(`dashboard.conversion.${conversion.status}.short`)}`;
  }
  private query(): JournalQuery {
    const params = this.route.snapshot.queryParamMap;
    return {
      dateFrom: params.get('dateFrom') ?? undefined,
      dateTo: params.get('dateTo') ?? undefined,
    };
  }
  private locale(): SupportedLanguage {
    return resolveLocale(this.transloco.getActiveLang());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    AccountingLegDisclosureComponent,
    DataViewComponent,
    PageHeaderComponent,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-journal-detail-page',
  template: `
    @if (entry(); as item) {
      <mmm-page-header
        [eyebrow]="'journal.detail.eyebrow' | transloco"
        [title]="typeLabel(item.economicType)"
        [description]="item.id"
      >
        <a class="secondary-button" routerLink="correct">{{
          'journal.correct.title' | transloco
        }}</a
        ><a class="danger-button" routerLink="reverse">{{ 'journal.reverse.title' | transloco }}</a>
      </mmm-page-header>
      <section class="detail-grid">
        <article class="finance-card">
          <h2>{{ 'journal.detail.entry' | transloco }}</h2>
          <dl>
            <div>
              <dt>{{ 'journal.fields.postedOn' | transloco }}</dt>
              <dd>{{ item.postedOn }}</dd>
            </div>
            <div>
              <dt>{{ 'journal.fields.effectiveAt' | transloco }}</dt>
              <dd>{{ item.effectiveAt }}</dd>
            </div>
            <div>
              <dt>{{ 'journal.fields.source' | transloco }}</dt>
              <dd>{{ item.source.module }} · {{ item.source.referenceId ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ 'journal.fields.note' | transloco }}</dt>
              <dd>{{ item.note ?? '—' }}</dd>
            </div>
          </dl>
        </article>
        <article class="finance-card">
          <mmm-accounting-leg-disclosure [label]="'journal.detail.legs' | transloco">
            <table class="finance-table">
              <thead>
                <tr>
                  <th>{{ 'journal.fields.side' | transloco }}</th>
                  <th>{{ 'journal.fields.account' | transloco }}</th>
                  <th>{{ 'journal.fields.amount' | transloco }}</th>
                </tr>
              </thead>
              <tbody>
                @for (leg of item.legs; track leg.id) {
                  <tr>
                    <td>{{ leg.side }}</td>
                    <td>{{ leg.accountId ?? '—' }}</td>
                    <td>{{ leg.amount }} {{ leg.currency }}</td>
                  </tr>
                }
              </tbody>
            </table></mmm-accounting-leg-disclosure
          >
        </article>
        <article class="finance-card">
          <h2>{{ 'journal.detail.conversion' | transloco }}</h2>
          @if (item.conversion; as conversion) {
            <dl>
              <div>
                <dt>{{ 'journal.fields.status' | transloco }}</dt>
                <dd>{{ conversion.status }}</dd>
              </div>
              <div>
                <dt>{{ 'journal.fields.sourceAmount' | transloco }}</dt>
                <dd>{{ conversion.sourceAmount }} {{ conversion.sourceCurrency }}</dd>
              </div>
              <div>
                <dt>{{ 'journal.fields.convertedAmount' | transloco }}</dt>
                <dd>
                  {{ conversion.convertedAmount ?? ('dashboard.unavailable' | transloco) }}
                  {{ conversion.targetCurrency }}
                </dd>
              </div>
              <div>
                <dt>{{ 'journal.fields.provenance' | transloco }}</dt>
                <dd>{{ conversion.provider ?? '—' }} · {{ conversion.rateAt ?? '—' }}</dd>
              </div>
            </dl>
          } @else {
            <p>{{ 'dashboard.unavailable' | transloco }}</p>
          }
        </article>
        @if (item.reversesEntryId || item.replacesEntryId) {
          <article class="finance-card">
            <h2>{{ 'journal.detail.history' | transloco }}</h2>
            <p>{{ 'journal.fields.reverses' | transloco }}: {{ item.reversesEntryId ?? '—' }}</p>
            <p>{{ 'journal.fields.replaces' | transloco }}: {{ item.replacesEntryId ?? '—' }}</p>
          </article>
        }
      </section>
    } @else {
      <mmm-data-view
        state="error"
        [loadingTitle]="'state.loading' | transloco"
        [loadingDescription]="'state.loading' | transloco"
        [emptyTitle]="'state.emptyTitle' | transloco"
        [emptyDescription]="'state.emptyDescription' | transloco"
        [errorTitle]="'journal.detail.notLoaded' | transloco"
        [errorDescription]="'journal.detail.openFromList' | transloco"
        [gatedTitle]="'state.disabled' | transloco"
        [gatedDescription]="'state.disabled' | transloco"
        [retryLabel]="'state.retry' | transloco"
        requestIdLabel=""
      />
      <a class="text-link" routerLink="/app/activity">{{ 'journal.back' | transloco }}</a>
    }
  `,
  styles: [
    `
      .detail-grid {
        display: grid;
        gap: 1rem;
      }
      .finance-card {
        padding: 1rem;
      }
      .finance-card dl {
        display: grid;
        gap: 0.75rem;
      }
      .finance-card dl div {
        display: grid;
        grid-template-columns: minmax(7rem, 1fr) 2fr;
        gap: 1rem;
      }
      .finance-card dt {
        color: var(--text-secondary);
      }
      .finance-card dd {
        margin: 0;
        overflow-wrap: anywhere;
      }
      @media (min-width: 64rem) {
        .detail-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }
    `,
  ],
})
export class JournalDetailPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly facade = inject(JournalFacade);
  private readonly transloco = inject(TranslocoService);
  protected readonly entry = computed(() =>
    this.facade.entry(this.route.snapshot.paramMap.get('id') ?? ''),
  );
  ngOnInit(): void {
    void this.facade.ensureEntry(this.route.snapshot.paramMap.get('id') ?? '');
  }
  protected typeLabel(type: string): string {
    return this.transloco.translate(`journal.types.${type}`);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CorrectionComparisonComponent,
    InlineAlertComponent,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    PageHeaderComponent,
    ReactiveFormsModule,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-journal-command-page',
  template: `
    <mmm-page-header
      [eyebrow]="'journal.eyebrow' | transloco"
      [title]="title()"
      [description]="description()"
    />
    <form class="command-form" [formGroup]="form" (ngSubmit)="review()">
      @if (correction && original(); as entry) {
        <mmm-correction-comparison
          [title]="'journal.correct.comparison' | transloco"
          [originalLabel]="'journal.correct.original' | transloco"
          [originalValue]="originalAmount(entry)"
          [replacementLabel]="'journal.correct.replacement' | transloco"
          [replacementValue]="
            form.controls.amount.value + ' ' + form.controls.currency.value.toUpperCase()
          "
          [explanation]="'journal.correct.history' | transloco"
        />
      }
      @if (commandError()) {
        <mmm-inline-alert tone="danger">{{ commandError() }}</mmm-inline-alert>
      }
      @if (commandUncertain()) {
        <mmm-inline-alert tone="warning">{{
          'journal.command.uncertain' | transloco
        }}</mmm-inline-alert>
      }
      <mat-form-field
        ><mat-label>{{ 'journal.fields.type' | transloco }}</mat-label
        ><mat-select formControlName="economicType">
          @for (type of types; track type) {
            <mat-option [value]="type">{{ typeLabel(type) }}</mat-option>
          }
        </mat-select></mat-form-field
      >
      <mat-form-field
        ><mat-label>{{ 'journal.fields.amount' | transloco }}</mat-label
        ><input matInput inputmode="decimal" formControlName="amount"
      /></mat-form-field>
      <mat-form-field
        ><mat-label>{{ 'journal.fields.currency' | transloco }}</mat-label
        ><input matInput maxlength="3" formControlName="currency"
      /></mat-form-field>
      <mat-form-field
        ><mat-label>{{ 'journal.fields.postedOn' | transloco }}</mat-label
        ><input matInput type="date" formControlName="postedOn"
      /></mat-form-field>
      <mat-form-field
        ><mat-label>{{ 'journal.fields.effectiveAt' | transloco }}</mat-label
        ><input matInput type="datetime-local" formControlName="effectiveAt"
      /></mat-form-field>
      @if (form.controls.economicType.value === 'internal_transfer') {
        <mat-form-field
          ><mat-label>{{ 'journal.fields.sourceAccount' | transloco }}</mat-label
          ><input matInput formControlName="sourceAccountId"
        /></mat-form-field>
        <mat-form-field
          ><mat-label>{{ 'journal.fields.destinationAccount' | transloco }}</mat-label
          ><input matInput formControlName="destinationAccountId"
        /></mat-form-field>
      } @else {
        <mat-form-field
          ><mat-label>{{ 'journal.fields.account' | transloco }}</mat-label
          ><input matInput formControlName="accountId"
        /></mat-form-field>
      }
      @if (form.controls.economicType.value === 'adjustment') {
        <mat-form-field
          ><mat-label>{{ 'journal.fields.direction' | transloco }}</mat-label
          ><mat-select formControlName="adjustmentDirection"
            ><mat-option value="increase">{{
              'journal.directions.increase' | transloco
            }}</mat-option
            ><mat-option value="decrease">{{
              'journal.directions.decrease' | transloco
            }}</mat-option></mat-select
          ></mat-form-field
        >
      }
      <mat-form-field
        ><mat-label>{{ 'journal.fields.category' | transloco }}</mat-label
        ><input matInput formControlName="categoryId"
      /></mat-form-field>
      <mat-form-field class="wide"
        ><mat-label>{{ 'journal.fields.note' | transloco }}</mat-label
        ><textarea matInput formControlName="note"></textarea>
      </mat-form-field>
      <div class="form-actions">
        <a class="text-link" routerLink="/app/activity">{{ 'journal.back' | transloco }}</a
        ><button class="action-button" type="submit" [disabled]="form.invalid || submitting()">
          {{ 'journal.command.review' | transloco }}
        </button>
      </div>
    </form>
  `,
  styles: [
    `
      .command-form {
        display: grid;
        gap: 1rem;
        max-width: 58rem;
        padding: 1rem;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-lg);
        background: var(--surface-raised);
      }
      .command-form mmm-inline-alert,
      .wide,
      .form-actions {
        grid-column: 1/-1;
      }
      .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      @media (min-width: 48rem) {
        .command-form {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }
    `,
  ],
})
export class JournalCommandPageComponent implements OnInit {
  protected readonly types = ECONOMIC_TYPES;
  private readonly fb = inject(FormBuilder).nonNullable;
  private readonly facade = inject(JournalFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly transloco = inject(TranslocoService);
  protected readonly correction = this.route.snapshot.data['mode'] === 'correct';
  private readonly id = this.route.snapshot.paramMap.get('id');
  protected readonly form = this.fb.group(
    {
      economicType: this.fb.control<CreateJournalEntryDto['economicType']>('external_expense'),
      amount: ['', [required, Validators.pattern(exactPositive)]],
      currency: ['', [required, Validators.pattern(/^[A-Za-z]{3}$/)]],
      postedOn: ['', required],
      effectiveAt: '',
      accountId: ['', Validators.pattern(uuid)],
      sourceAccountId: ['', Validators.pattern(uuid)],
      destinationAccountId: ['', Validators.pattern(uuid)],
      adjustmentDirection: this.fb.control<'' | 'increase' | 'decrease'>(''),
      categoryId: ['', Validators.pattern(uuid)],
      note: '',
    },
    { validators: journalShapeValidator },
  );
  protected readonly title = signal('');
  protected readonly original = computed(() => (this.id ? this.facade.entry(this.id) : null));
  protected readonly description = signal('');
  protected readonly submitting = computed(
    () =>
      (this.correction ? this.facade.correctCommand.state() : this.facade.createCommand.state())
        .phase === 'submitting',
  );
  protected readonly commandUncertain = computed(
    () =>
      (this.correction ? this.facade.correctCommand.state() : this.facade.createCommand.state())
        .phase === 'uncertain',
  );
  protected readonly commandError = computed(() =>
    (this.correction ? this.facade.correctCommand.state() : this.facade.createCommand.state())
      .phase === 'failed'
      ? this.transloco.translate('journal.command.failed')
      : '',
  );

  ngOnInit(): void {
    this.title.set(
      this.transloco.translate(this.correction ? 'journal.correct.title' : 'journal.newTitle'),
    );
    this.description.set(
      this.transloco.translate(
        this.correction ? 'journal.correct.description' : 'journal.newDescription',
      ),
    );
    if (this.correction && this.id) {
      void this.facade.ensureEntry(this.id).then(() => this.prefill(this.facade.entry(this.id!)));
    }
  }
  protected typeLabel(type: string): string {
    return this.transloco.translate(`journal.types.${type}`);
  }
  protected originalAmount(entry: JournalEntryResponseDto): string {
    return `${entry.conversion?.sourceAmount ?? entry.legs[0]?.amount ?? '—'} ${entry.conversion?.sourceCurrency ?? entry.legs[0]?.currency ?? ''}`;
  }
  protected async review(): Promise<void> {
    if (this.form.invalid) return;
    const body = this.body();
    const confirmed = await firstValueFrom(
      this.dialog
        .open<FinancialCommandReviewDialogComponent, FinancialCommandReviewData, boolean>(
          FinancialCommandReviewDialogComponent,
          {
            data: {
              title: this.title(),
              description: this.transloco.translate('journal.command.confirmDescription'),
              confirmLabel: this.transloco.translate('journal.command.confirm'),
              cancelLabel: this.transloco.translate('journal.command.cancel'),
              rows: [
                {
                  label: this.transloco.translate('journal.fields.type'),
                  value: this.typeLabel(body.economicType),
                },
                {
                  label: this.transloco.translate('journal.fields.amount'),
                  value: `${body.amount} ${body.currency}`,
                },
                {
                  label: this.transloco.translate('journal.fields.postedOn'),
                  value: body.postedOn,
                },
              ],
            },
          },
        )
        .afterClosed(),
    );
    if (!confirmed) return;
    if (this.correction && this.id) {
      const result = await this.facade.correct(this.id, body);
      if (result) await this.router.navigate(['/app/activity', result.replacement.id]);
    } else {
      const result = await this.facade.create(body);
      if (result) await this.router.navigate(['/app/activity', result.id]);
    }
  }
  private body(): CreateJournalEntryDto {
    const value = this.form.getRawValue();
    return {
      economicType: value.economicType,
      amount: value.amount,
      currency: value.currency.toUpperCase(),
      postedOn: value.postedOn,
      ...(value.effectiveAt ? { effectiveAt: new Date(value.effectiveAt).toISOString() } : {}),
      ...(value.accountId ? { accountId: value.accountId } : {}),
      ...(value.sourceAccountId ? { sourceAccountId: value.sourceAccountId } : {}),
      ...(value.destinationAccountId ? { destinationAccountId: value.destinationAccountId } : {}),
      ...(value.adjustmentDirection ? { adjustmentDirection: value.adjustmentDirection } : {}),
      ...(value.categoryId ? { categoryId: value.categoryId } : {}),
      ...(value.note.trim() ? { note: value.note.trim() } : {}),
    };
  }
  private prefill(entry: JournalEntryResponseDto | null): void {
    if (!entry) return;
    const debit = entry.legs.find((leg) => leg.side === 'debit')?.accountId ?? '';
    const credit = entry.legs.find((leg) => leg.side === 'credit')?.accountId ?? '';
    this.form.patchValue({
      economicType: entry.economicType as CreateJournalEntryDto['economicType'],
      amount: entry.conversion?.sourceAmount ?? entry.legs[0]?.amount ?? '',
      currency: entry.conversion?.sourceCurrency ?? entry.legs[0]?.currency ?? '',
      postedOn: entry.postedOn,
      effectiveAt: entry.effectiveAt.slice(0, 16),
      accountId: debit || credit,
      sourceAccountId: credit,
      destinationAccountId: debit,
      categoryId: entry.categoryId ?? '',
      note: entry.note ?? '',
    });
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    InlineAlertComponent,
    MatFormFieldModule,
    MatInputModule,
    PageHeaderComponent,
    ReactiveFormsModule,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-journal-reverse-page',
  template: `<mmm-page-header
      [title]="'journal.reverse.title' | transloco"
      [description]="'journal.reverse.description' | transloco"
    />
    <form class="command-form" [formGroup]="form" (ngSubmit)="submit()">
      <mmm-inline-alert tone="warning">{{ 'journal.reverse.warning' | transloco }}</mmm-inline-alert
      ><mat-form-field
        ><mat-label>{{ 'journal.fields.postedOn' | transloco }}</mat-label
        ><input matInput type="date" formControlName="postedOn" /></mat-form-field
      ><mat-form-field
        ><mat-label>{{ 'journal.fields.effectiveAt' | transloco }}</mat-label
        ><input matInput type="datetime-local" formControlName="effectiveAt" /></mat-form-field
      ><mat-form-field
        ><mat-label>{{ 'journal.fields.note' | transloco }}</mat-label
        ><textarea matInput formControlName="note"></textarea>
      </mat-form-field>
      <div>
        <a class="text-link" routerLink="..">{{ 'journal.back' | transloco }}</a
        ><button class="danger-button" type="submit" [disabled]="form.invalid">
          {{ 'journal.reverse.confirm' | transloco }}
        </button>
      </div>
    </form>`,
  styles: [
    `
      .command-form {
        display: grid;
        gap: 1rem;
        max-width: 42rem;
        padding: 1rem;
        background: var(--surface-raised);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-lg);
      }
      .command-form div {
        display: flex;
        justify-content: space-between;
      }
    `,
  ],
})
export class JournalReversePageComponent {
  private readonly fb = inject(FormBuilder).nonNullable;
  private readonly route = inject(ActivatedRoute);
  private readonly facade = inject(JournalFacade);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly transloco = inject(TranslocoService);
  protected readonly form = this.fb.group({
    postedOn: ['', required],
    effectiveAt: '',
    note: '',
  });
  protected async submit(): Promise<void> {
    if (this.form.invalid) return;
    const id = this.route.snapshot.paramMap.get('id');
    if (!id) return;
    const value = this.form.getRawValue();
    const confirmed = await firstValueFrom(
      this.dialog
        .open<FinancialCommandReviewDialogComponent, FinancialCommandReviewData, boolean>(
          FinancialCommandReviewDialogComponent,
          {
            data: {
              title: this.transloco.translate('journal.reverse.title'),
              description: this.transloco.translate('journal.reverse.warning'),
              confirmLabel: this.transloco.translate('journal.reverse.confirm'),
              cancelLabel: this.transloco.translate('journal.command.cancel'),
              rows: [
                {
                  label: this.transloco.translate('journal.fields.postedOn'),
                  value: value.postedOn,
                },
              ],
            },
          },
        )
        .afterClosed(),
    );
    if (!confirmed) return;
    const result = await this.facade.reverse(id, {
      postedOn: value.postedOn,
      ...(value.effectiveAt ? { effectiveAt: new Date(value.effectiveAt).toISOString() } : {}),
      ...(value.note.trim() ? { note: value.note.trim() } : {}),
    });
    if (result) await this.router.navigate(['/app/activity', result.id]);
  }
}

function journalShapeValidator(control: AbstractControl): ValidationErrors | null {
  const type = control.get('economicType')?.value as
    CreateJournalEntryDto['economicType'] | undefined;
  if (type === 'internal_transfer') {
    const source = String(control.get('sourceAccountId')?.value ?? '');
    const destination = String(control.get('destinationAccountId')?.value ?? '');
    return source && destination && source !== destination ? null : { transferAccounts: true };
  }
  if (!control.get('accountId')?.value) return { accountRequired: true };
  if (type === 'adjustment' && !control.get('adjustmentDirection')?.value)
    return { directionRequired: true };
  return null;
}

function required(control: AbstractControl): ValidationErrors | null {
  return Validators.required(control);
}

function toDataViewState(status: JournalStateName): DataViewState {
  if (status === 'idle') return 'loading';
  if (status === 'ready') return 'success';
  return status;
}

type JournalStateName = 'idle' | 'loading' | 'ready' | 'empty' | 'error';
