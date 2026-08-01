import { ChangeDetectionStrategy, Component, inject, input, output } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';

export interface FinancialCommandReviewData {
  readonly title: string;
  readonly description: string;
  readonly confirmLabel: string;
  readonly cancelLabel: string;
  readonly rows: readonly { readonly label: string; readonly value: string }[];
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatDialogModule],
  selector: 'mmm-financial-command-review-dialog',
  template: `
    <h2 mat-dialog-title>{{ data.title }}</h2>
    <mat-dialog-content>
      <p>{{ data.description }}</p>
      <dl class="review-list">
        @for (row of data.rows; track row.label) {
          <div>
            <dt>{{ row.label }}</dt>
            <dd>{{ row.value }}</dd>
          </div>
        }
      </dl>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button type="button" mat-button (click)="close(false)">{{ data.cancelLabel }}</button>
      <button type="button" mat-flat-button color="primary" (click)="close(true)">
        {{ data.confirmLabel }}
      </button>
    </mat-dialog-actions>
  `,
})
export class FinancialCommandReviewDialogComponent {
  protected readonly data = inject<FinancialCommandReviewData>(MAT_DIALOG_DATA);
  private readonly dialog = inject(MatDialogRef<FinancialCommandReviewDialogComponent, boolean>);

  protected close(value: boolean): void {
    this.dialog.close(value);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-cursor-pager',
  template: `
    @if (nextCursor()) {
      <button
        class="secondary-button"
        type="button"
        [disabled]="loading()"
        (click)="loadMore.emit()"
      >
        {{ loading() ? loadingLabel() : loadMoreLabel() }}
      </button>
    }
  `,
})
export class CursorPagerComponent {
  readonly nextCursor = input<string | null>(null);
  readonly loading = input(false);
  readonly loadMoreLabel = input.required<string>();
  readonly loadingLabel = input.required<string>();
  readonly loadMore = output<void>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-journal-entry-card',
  template: `<article class="journal-entry-card">
    <div>
      <strong>{{ title() }}</strong
      ><small>{{ metadata() }}</small>
    </div>
    <div class="journal-amount">
      <strong>{{ amount() }}</strong
      ><small>{{ status() }}</small>
    </div>
    <ng-content />
  </article>`,
  styles: [
    `
      .journal-entry-card {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
      }
      .journal-entry-card > div {
        display: grid;
        gap: 0.25rem;
      }
      .journal-entry-card small {
        color: var(--text-secondary);
      }
      .journal-amount {
        text-align: right;
      }
    `,
  ],
})
export class JournalEntryCardComponent {
  readonly title = input.required<string>();
  readonly metadata = input.required<string>();
  readonly amount = input.required<string>();
  readonly status = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-accounting-leg-disclosure',
  template: `<details class="leg-disclosure" open>
    <summary>{{ label() }}</summary>
    <ng-content />
  </details>`,
  styles: [
    `
      .leg-disclosure summary {
        cursor: pointer;
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
      }
    `,
  ],
})
export class AccountingLegDisclosureComponent {
  readonly label = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-correction-comparison',
  template: `<section class="correction-comparison" aria-labelledby="correction-comparison-title">
    <h2 id="correction-comparison-title">{{ title() }}</h2>
    <div>
      <article>
        <h3>{{ originalLabel() }}</h3>
        <strong>{{ originalValue() }}</strong>
      </article>
      <article>
        <h3>{{ replacementLabel() }}</h3>
        <strong>{{ replacementValue() }}</strong>
      </article>
    </div>
    <p>{{ explanation() }}</p>
  </section>`,
  styles: [
    `
      :host {
        grid-column: 1/-1;
      }
      .correction-comparison {
        padding: 1rem;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-md);
      }
      .correction-comparison > div {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }
      .correction-comparison strong {
        overflow-wrap: anywhere;
      }
    `,
  ],
})
export class CorrectionComparisonComponent {
  readonly title = input.required<string>();
  readonly originalLabel = input.required<string>();
  readonly originalValue = input.required<string>();
  readonly replacementLabel = input.required<string>();
  readonly replacementValue = input.required<string>();
  readonly explanation = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-period-navigator',
  template: `<nav class="period-navigator" [attr.aria-label]="label()">
    <button class="secondary-button" type="button" (click)="previous.emit()">
      {{ previousLabel() }}</button
    ><strong>{{ period() }}</strong
    ><button class="secondary-button" type="button" (click)="next.emit()">{{ nextLabel() }}</button>
  </nav>`,
  styles: [
    `
      .period-navigator {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-block: 1rem;
      }
      .period-navigator strong {
        text-align: center;
      }
    `,
  ],
})
export class PeriodNavigatorComponent {
  readonly label = input.required<string>();
  readonly previousLabel = input.required<string>();
  readonly nextLabel = input.required<string>();
  readonly period = input.required<string>();
  readonly previous = output<void>();
  readonly next = output<void>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-report-table',
  template: `<table class="finance-table report-table">
    <caption>
      {{
        caption()
      }}
    </caption>
    <thead>
      <tr>
        <th scope="col">{{ periodHeading() }}</th>
        <th scope="col">{{ valueHeading() }}</th>
      </tr>
    </thead>
    <tbody>
      @for (row of rows(); track row.period) {
        <tr>
          <th scope="row">{{ row.period }}</th>
          <td>{{ row.value }}</td>
        </tr>
      }
    </tbody>
  </table>`,
})
export class ReportTableComponent {
  readonly caption = input.required<string>();
  readonly periodHeading = input.required<string>();
  readonly valueHeading = input.required<string>();
  readonly rows = input.required<readonly { readonly period: string; readonly value: string }[]>();
}
