/* eslint-disable @typescript-eslint/unbound-method -- Angular validators are passed by design. */
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslocoPipe, TranslocoService } from '@jsverse/transloco';
import type { BasicIncomeResponseDto } from '@mymoneymap/generated-api-client/models/basic-income-response-dto';
import type { BudgetRuleResponseDto } from '@mymoneymap/generated-api-client/models/budget-rule-response-dto';
import type { CategoryResponseDto } from '@mymoneymap/generated-api-client/models/category-response-dto';
import type { RecurringRuleResponseDto } from '@mymoneymap/generated-api-client/models/recurring-rule-response-dto';
import {
  CategoryChipComponent,
  EntitlementLimitBannerComponent,
  ForecastOccurrencesComponent,
  InlineAlertComponent,
  PageHeaderComponent,
  SignedVarianceComponent,
} from '@mymoneymap/web-design-system';
import { PlanningFacade } from './planning.facade';
import { CommandErrorComponent } from './command-error';
import { buildSupportedRRule, describeSupportedRRule, type SupportedFrequency } from './rrule';

const PAGE_IMPORTS = [
  ReactiveFormsModule,
  MatButtonModule,
  MatFormFieldModule,
  MatInputModule,
  MatSelectModule,
  PageHeaderComponent,
  TranslocoPipe,
  CommandErrorComponent,
] as const;
const MONEY_PATTERN = /^(?:0*[1-9]\d*)(?:\.\d+)?$|^0*\.\d*[1-9]\d*$/;
const PERCENT_PATTERN = /^(?:0|[1-9]\d?)(?:\.\d{1,4})?$|^100(?:\.0{1,4})?$/;
const DATE_PATTERN = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, RouterLink, TranslocoPipe],
  selector: 'mmm-planning-hub-page',
  template: `
    <section class="page-shell">
      <mmm-page-header
        [title]="'planning.hub.title' | transloco"
        [description]="'planning.hub.description' | transloco"
      />
      <div class="planning-grid">
        @for (item of links; track item.route) {
          <a class="finance-card planning-link" [routerLink]="item.route">
            <h2>{{ item.title | transloco }}</h2>
            <p>{{ item.description | transloco }}</p>
            <strong>{{ count(item.id) }}</strong>
          </a>
        }
      </div>
      <p class="finance-disclaimer">{{ 'planning.hub.boundary' | transloco }}</p>
    </section>
  `,
})
export class PlanningHubPageComponent implements OnInit {
  private readonly facade = inject(PlanningFacade);
  protected readonly links = [
    {
      id: 'budget',
      route: 'budget',
      title: 'planning.budget.title',
      description: 'planning.budget.description',
    },
    {
      id: 'categories',
      route: 'categories',
      title: 'planning.categories.title',
      description: 'planning.categories.description',
    },
    {
      id: 'income',
      route: 'income',
      title: 'planning.income.title',
      description: 'planning.income.description',
    },
    {
      id: 'schedules',
      route: 'schedules',
      title: 'planning.schedules.title',
      description: 'planning.schedules.description',
    },
  ] as const;
  ngOnInit(): void {
    void this.facade.loadHub();
  }
  protected count(id: (typeof this.links)[number]['id']): number | string {
    const state =
      id === 'budget'
        ? this.facade.budget()
        : id === 'categories'
          ? this.facade.categories()
          : id === 'income'
            ? this.facade.incomes()
            : this.facade.schedules();
    return state.status === 'loading' || state.status === 'idle'
      ? '…'
      : (state.data?.items.length ?? 0);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, InlineAlertComponent, SignedVarianceComponent],
  selector: 'mmm-budget-page',
  template: `
    <section class="page-shell">
      <mmm-page-header
        [title]="'planning.budget.title' | transloco"
        [description]="'planning.budget.description' | transloco"
      />
      <form class="filter-bar" [formGroup]="monthForm" (ngSubmit)="load()">
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.budget.month' | transloco }}</mat-label
          ><input matInput type="month" formControlName="month"
        /></mat-form-field>
        <button mat-stroked-button type="submit">{{ 'state.apply' | transloco }}</button>
      </form>
      <mmm-command-error [facade]="facade" />
      @if (!facade.canEditCashFlowRules()) {
        <mmm-inline-alert tone="information">{{
          'planning.budget.readOnly' | transloco
        }}</mmm-inline-alert>
      }
      @if (facade.budget().status === 'loading') {
        <p role="status">{{ 'planning.states.loading' | transloco }}</p>
      }
      @if (facade.budget().status === 'error') {
        <mmm-inline-alert tone="danger"
          >{{ 'planning.states.error' | transloco }}
          {{ facade.budget().requestId }}</mmm-inline-alert
        >
      }
      @if (facade.budget().data; as budget) {
        <section class="finance-card allocation" [attr.data-status]="budget.allocation.status">
          <h2>{{ 'planning.budget.allocation' | transloco }}</h2>
          <strong>{{ budget.allocation.totalPercent }}%</strong>
          @if (budget.allocation.status === 'over_allocated') {
            <p>
              {{
                'planning.budget.overAllocated'
                  | transloco: { amount: budget.allocation.overAllocatedBy }
              }}
            </p>
          }
        </section>
        @if (budget.period; as period) {
          <p class="data-provenance">
            {{ period.month }} · {{ period.currency }} ·
            {{ 'planning.budget.forecastIncome' | transloco }}:
            {{ period.forecastIncome ?? ('planning.states.unavailable' | transloco) }} ({{
              period.forecastIncomeStatus
            }})
          </p>
        }
        <div class="entity-list">
          @for (rule of budget.items; track rule.id) {
            <article class="finance-card">
              <div class="entity-heading">
                <div>
                  <h2>{{ rule.label }}</h2>
                  <p>{{ rule.percent }}% · {{ rule.targetHint }}</p>
                </div>
                @if (facade.canEditCashFlowRules()) {
                  <div class="actions">
                    <button mat-button type="button" (click)="edit(rule)">
                      {{ 'state.edit' | transloco }}</button
                    ><button mat-button type="button" (click)="remove(rule.id)">
                      {{ 'state.delete' | transloco }}
                    </button>
                  </div>
                }
              </div>
              @if (rule.plan; as plan) {
                <dl class="metric-row">
                  <div>
                    <dt>{{ 'planning.budget.planned' | transloco }}</dt>
                    <dd>{{ plan.plannedAmount ?? ('planning.states.unavailable' | transloco) }}</dd>
                  </div>
                  <div>
                    <dt>{{ 'planning.budget.spent' | transloco }}</dt>
                    <dd>
                      {{
                        plan.assignedCategorySpending ?? ('planning.states.unavailable' | transloco)
                      }}
                    </dd>
                  </div>
                  <div>
                    <dt>{{ 'planning.budget.variance' | transloco }}</dt>
                    <dd>
                      @if (plan.signedVariance) {
                        <mmm-signed-variance
                          [label]="'planning.budget.variance' | transloco"
                          [value]="plan.signedVariance + ' ' + plan.currency"
                          [sign]="varianceSign(plan.signedVariance)"
                        />
                      } @else {
                        {{ 'planning.states.unavailable' | transloco }}
                      }
                    </dd>
                  </div>
                </dl>
                <p class="data-badge" [attr.data-kind]="plan.status">{{ plan.status }}</p>
              }
            </article>
          } @empty {
            <p>{{ 'planning.budget.empty' | transloco }}</p>
          }
        </div>
      }
      @if (facade.canEditCashFlowRules()) {
        <form class="feature-form finance-card" [formGroup]="form" (ngSubmit)="save()">
          <h2>
            {{
              editingId()
                ? ('planning.budget.editRule' | transloco)
                : ('planning.budget.addRule' | transloco)
            }}
          </h2>
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.label' | transloco }}</mat-label
            ><input matInput formControlName="label"
          /></mat-form-field>
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.percent' | transloco }}</mat-label
            ><input matInput inputmode="decimal" formControlName="percent"
          /></mat-form-field>
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.hint' | transloco }}</mat-label
            ><input matInput formControlName="targetHint"
          /></mat-form-field>
          <div class="actions">
            <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
              {{ 'state.save' | transloco }}
            </button>
            @if (editingId()) {
              <button mat-button type="button" (click)="cancel()">
                {{ 'state.cancel' | transloco }}
              </button>
            }
          </div>
        </form>
      }
    </section>
  `,
})
export class BudgetPageComponent implements OnInit {
  protected readonly facade = inject(PlanningFacade);
  private readonly i18n = inject(TranslocoService);
  protected readonly editingId = signal<string | null>(null);
  protected readonly monthForm = new FormGroup({
    month: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^\d{4}-(?:0[1-9]|1[0-2])$/)],
    }),
  });
  protected readonly form = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    percent: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(PERCENT_PATTERN)],
    }),
    targetHint: new FormControl('', { nonNullable: true, validators: [Validators.maxLength(500)] }),
  });
  ngOnInit(): void {
    void this.facade.loadBudget();
  }
  protected load(): void {
    void this.facade.loadBudget(this.monthForm.controls.month.value || undefined);
  }
  protected edit(rule: BudgetRuleResponseDto): void {
    this.editingId.set(rule.id);
    this.form.setValue({
      label: rule.label,
      percent: rule.percent,
      targetHint: rule.targetHint ?? '',
    });
  }
  protected cancel(): void {
    this.editingId.set(null);
    this.form.reset();
  }
  protected async save(): Promise<void> {
    if (this.form.invalid) return;
    const value = this.form.getRawValue();
    const body = { ...value, targetHint: value.targetHint || null };
    const ok = this.editingId()
      ? await this.facade.updateRule(this.editingId()!, body)
      : await this.facade.createRule(body);
    if (ok) this.cancel();
  }
  protected remove(id: string): void {
    if (globalThis.confirm(this.i18n.translate('planning.confirm.rule')))
      void this.facade.deleteRule(id);
  }
  protected varianceSign(value: string): 'positive' | 'negative' | 'zero' {
    return value.startsWith('-') ? 'negative' : /^\+?0(?:\.0+)?$/.test(value) ? 'zero' : 'positive';
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ...PAGE_IMPORTS,
    CategoryChipComponent,
    EntitlementLimitBannerComponent,
    InlineAlertComponent,
  ],
  selector: 'mmm-categories-page',
  template: `
    <section class="page-shell">
      <mmm-page-header
        [title]="'planning.categories.title' | transloco"
        [description]="'planning.categories.description' | transloco"
      />
      <mmm-command-error [facade]="facade" />
      @if (facade.categoryEntitlement(); as entitlement) {
        <mmm-entitlement-limit-banner
          [title]="'planning.categories.quotaTitle' | transloco"
          [description]="quotaDescription()"
          [reached]="quotaReached()"
        />
      }
      @if (facade.categories().status === 'loading') {
        <p role="status">{{ 'planning.states.loading' | transloco }}</p>
      }
      @if (facade.categories().status === 'error') {
        <mmm-inline-alert tone="danger"
          >{{ 'planning.states.error' | transloco }}
          {{ facade.categories().requestId }}</mmm-inline-alert
        >
      }
      <div class="entity-list">
        @for (category of facade.categories().data?.items ?? []; track category.id) {
          <article class="finance-card entity-heading">
            <div>
              <mmm-category-chip
                [label]="category.label"
                [color]="category.color"
                [kind]="category.kind"
              />
              <p>
                {{ category.budgetRuleLabel ?? ('planning.categories.unassigned' | transloco) }}
              </p>
              @if (category.protected) {
                <span class="data-badge">{{ 'planning.categories.protected' | transloco }}</span>
              }
              @if (!category.protected && category.budgetRuleId !== null) {
                <span class="data-badge">{{ 'planning.categories.referenced' | transloco }}</span>
              }
            </div>
            <div class="actions">
              <button
                mat-button
                type="button"
                [disabled]="category.protected"
                (click)="edit(category)"
              >
                {{ 'state.edit' | transloco }}</button
              ><button
                mat-button
                type="button"
                [disabled]="category.protected || category.budgetRuleId !== null"
                (click)="remove(category)"
              >
                {{ 'state.delete' | transloco }}
              </button>
            </div>
          </article>
        } @empty {
          <p>{{ 'planning.categories.empty' | transloco }}</p>
        }
      </div>
      <form class="feature-form finance-card" [formGroup]="form" (ngSubmit)="save()">
        <h2>
          {{
            editingId()
              ? ('planning.categories.edit' | transloco)
              : ('planning.categories.add' | transloco)
          }}
        </h2>
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.label' | transloco }}</mat-label
          ><input matInput formControlName="label"
        /></mat-form-field>
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.kind' | transloco }}</mat-label
          ><mat-select formControlName="kind"
            ><mat-option value="spending">{{ 'planning.kinds.spending' | transloco }}</mat-option
            ><mat-option value="income">{{
              'planning.kinds.income' | transloco
            }}</mat-option></mat-select
          ></mat-form-field
        >
        <label
          >{{ 'planning.fields.color' | transloco }} <input type="color" formControlName="color"
        /></label>
        @if (form.controls.kind.value === 'spending' && facade.canEditCashFlowRules()) {
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.categories.rule' | transloco }}</mat-label
            ><mat-select formControlName="budgetRuleId"
              ><mat-option value="">{{ 'planning.categories.unassigned' | transloco }}</mat-option>
              @for (rule of facade.budget().data?.items ?? []; track rule.id) {
                <mat-option [value]="rule.id">{{ rule.label }}</mat-option>
              }
            </mat-select></mat-form-field
          >
        }
        <div class="actions">
          <button
            mat-flat-button
            type="submit"
            [disabled]="form.invalid || facade.pending() || (!editingId() && quotaReached())"
          >
            {{ 'state.save' | transloco }}
          </button>
          @if (editingId()) {
            <button mat-button type="button" (click)="cancel()">
              {{ 'state.cancel' | transloco }}
            </button>
          }
        </div>
      </form>
    </section>
  `,
})
export class CategoriesPageComponent implements OnInit {
  protected readonly facade = inject(PlanningFacade);
  private readonly i18n = inject(TranslocoService);
  protected readonly editingId = signal<string | null>(null);
  protected readonly form = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    kind: new FormControl<'income' | 'spending'>('spending', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    color: new FormControl('#2563eb', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^#[0-9a-fA-F]{6}$/)],
    }),
    budgetRuleId: new FormControl('', { nonNullable: true }),
  });
  protected readonly quotaReached = computed(() => {
    const limit = this.facade.categoryEntitlement()?.limit;
    return (
      limit !== null &&
      limit !== undefined &&
      (this.facade.categories().data?.items.length ?? 0) >= limit
    );
  });
  protected readonly quotaDescription = computed(() => {
    const limit = this.facade.categoryEntitlement()?.limit;
    const used = this.facade.categories().data?.items.length ?? 0;
    return limit === null
      ? this.i18n.translate('planning.quota.unlimited', { used })
      : this.i18n.translate('planning.quota.limited', { used, limit: limit ?? 0 });
  });
  ngOnInit(): void {
    void Promise.all([this.facade.loadCategories(), this.facade.loadBudget()]);
  }
  protected edit(category: CategoryResponseDto): void {
    this.editingId.set(category.id);
    this.form.setValue({
      label: category.label,
      kind: category.kind,
      color: category.color,
      budgetRuleId: category.budgetRuleId ?? '',
    });
  }
  protected cancel(): void {
    this.editingId.set(null);
    this.form.reset({ label: '', kind: 'spending', color: '#2563eb', budgetRuleId: '' });
  }
  protected async save(): Promise<void> {
    if (this.form.invalid) return;
    const value = this.form.getRawValue();
    const before = new Set(this.facade.categories().data?.items.map((item) => item.id));
    const existingId = this.editingId();
    const ok = existingId
      ? await this.facade.updateCategory(existingId, {
          label: value.label,
          kind: value.kind,
          color: value.color,
        })
      : await this.facade.createCategory({
          label: value.label,
          kind: value.kind,
          color: value.color,
        });
    const categoryId =
      existingId ?? this.facade.categories().data?.items.find((item) => !before.has(item.id))?.id;
    if (ok && categoryId && value.kind === 'spending' && this.facade.canEditCashFlowRules())
      await this.facade.assignRule(categoryId, value.budgetRuleId || null);
    if (ok) this.cancel();
  }
  protected remove(category: CategoryResponseDto): void {
    if (
      !category.protected &&
      category.budgetRuleId === null &&
      globalThis.confirm(this.i18n.translate('planning.confirm.category'))
    )
      void this.facade.deleteCategory(category.id);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, InlineAlertComponent],
  selector: 'mmm-income-page',
  template: `
    <section class="page-shell">
      <mmm-page-header
        [title]="'planning.income.title' | transloco"
        [description]="'planning.income.description' | transloco"
      />
      <mmm-inline-alert tone="information">{{
        'planning.income.planningOnly' | transloco
      }}</mmm-inline-alert
      ><mmm-command-error [facade]="facade" />
      @if (facade.incomes().status === 'loading') {
        <p role="status">{{ 'planning.states.loading' | transloco }}</p>
      }
      @if (facade.incomes().status === 'error') {
        <mmm-inline-alert tone="danger"
          >{{ 'planning.states.error' | transloco }}
          {{ facade.incomes().requestId }}</mmm-inline-alert
        >
      }
      <div class="entity-list">
        @for (income of facade.incomes().data?.items ?? []; track income.id) {
          <article class="finance-card entity-heading">
            <div>
              <h2>{{ income.label }}</h2>
              <p>
                {{ income.amount }} {{ income.currency }} · {{ income.validFrom }} —
                {{ income.validTo ?? ('planning.income.openEnded' | transloco) }}
              </p>
              <small>{{ income.categoryLabel }}</small>
            </div>
            <div class="actions">
              <button mat-button type="button" (click)="edit(income)">
                {{ 'state.edit' | transloco }}</button
              ><button mat-button type="button" (click)="remove(income.id)">
                {{ 'state.delete' | transloco }}
              </button>
            </div>
          </article>
        } @empty {
          <p>{{ 'planning.income.empty' | transloco }}</p>
        }
      </div>
      <form class="feature-form finance-card" [formGroup]="form" (ngSubmit)="save()">
        <h2>
          {{
            editingId() ? ('planning.income.edit' | transloco) : ('planning.income.add' | transloco)
          }}
        </h2>
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.label' | transloco }}</mat-label
          ><input matInput formControlName="label" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.amount' | transloco }}</mat-label
          ><input matInput inputmode="decimal" formControlName="amount" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.currency' | transloco }}</mat-label
          ><mat-select formControlName="currency">
            @for (currency of facade.currencies().data?.items ?? []; track currency.code) {
              <mat-option [value]="currency.code"
                >{{ currency.code }} — {{ currency.name }}</mat-option
              >
            }
          </mat-select></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.categoryId' | transloco }}</mat-label
          ><mat-select formControlName="categoryId"
            ><mat-option value="">—</mat-option>
            @for (category of incomeCategories(); track category.id) {
              <mat-option [value]="category.id">{{ category.label }}</mat-option>
            }
          </mat-select></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.validFrom' | transloco }}</mat-label
          ><input matInput type="date" formControlName="validFrom" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.validTo' | transloco }}</mat-label
          ><input matInput type="date" formControlName="validTo"
        /></mat-form-field>
        <div class="actions">
          <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
            {{ 'state.save' | transloco }}
          </button>
          @if (editingId()) {
            <button mat-button type="button" (click)="cancel()">
              {{ 'state.cancel' | transloco }}
            </button>
          }
        </div>
      </form>
    </section>
  `,
})
export class IncomePageComponent implements OnInit {
  protected readonly facade = inject(PlanningFacade);
  private readonly i18n = inject(TranslocoService);
  protected readonly editingId = signal<string | null>(null);
  protected readonly form = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    amount: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(MONEY_PATTERN)],
    }),
    currency: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^[A-Z]{3}$/)],
    }),
    categoryId: new FormControl('', { nonNullable: true }),
    validFrom: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(DATE_PATTERN)],
    }),
    validTo: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^\d{4}-\d{2}-\d{2}$/)],
    }),
  });
  protected readonly incomeCategories = computed(
    () =>
      this.facade.categories().data?.items.filter((category) => category.kind === 'income') ?? [],
  );
  ngOnInit(): void {
    void Promise.all([
      this.facade.loadIncomes(),
      this.facade.loadCurrencies(),
      this.facade.loadCategories(),
    ]);
  }
  protected edit(income: BasicIncomeResponseDto): void {
    this.editingId.set(income.id);
    this.form.setValue({
      label: income.label,
      amount: income.amount,
      currency: income.currency,
      categoryId: income.categoryId ?? '',
      validFrom: income.validFrom,
      validTo: income.validTo ?? '',
    });
  }
  protected cancel(): void {
    this.editingId.set(null);
    this.form.reset();
  }
  protected async save(): Promise<void> {
    if (this.form.invalid) return;
    const value = this.form.getRawValue();
    const body = {
      ...value,
      currency: value.currency.toUpperCase(),
      categoryId: value.categoryId || null,
      validTo: value.validTo || null,
    };
    const ok = this.editingId()
      ? await this.facade.updateIncome(this.editingId()!, body)
      : await this.facade.createIncome(body);
    if (ok) this.cancel();
  }
  protected remove(id: string): void {
    if (globalThis.confirm(this.i18n.translate('planning.confirm.income')))
      void this.facade.deleteIncome(id);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ...PAGE_IMPORTS,
    ForecastOccurrencesComponent,
    InlineAlertComponent,
    RouterLink,
    EntitlementLimitBannerComponent,
  ],
  selector: 'mmm-schedules-page',
  template: `
    <section class="page-shell">
      <mmm-page-header
        [title]="'planning.schedules.title' | transloco"
        [description]="'planning.schedules.description' | transloco"
      />
      @if (facade.scheduleEntitlement(); as entitlement) {
        <mmm-entitlement-limit-banner
          [title]="'planning.schedules.quotaTitle' | transloco"
          [description]="quotaDescription()"
          [reached]="quotaReached()"
        />
      }
      <a
        mat-flat-button
        [routerLink]="quotaReached() ? null : 'new'"
        [class.disabled-link]="quotaReached()"
        [attr.aria-disabled]="quotaReached()"
        >{{ 'planning.schedules.add' | transloco }}</a
      >
      <form class="filter-bar" [formGroup]="range" (ngSubmit)="loadForecast()">
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.from' | transloco }}</mat-label
          ><input matInput type="date" formControlName="from" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.to' | transloco }}</mat-label
          ><input matInput type="date" formControlName="to" /></mat-form-field
        ><button mat-stroked-button type="submit" [disabled]="range.invalid">
          {{ 'planning.schedules.preview' | transloco }}
        </button>
      </form>
      <mmm-command-error [facade]="facade" /><mmm-inline-alert tone="information">{{
        'planning.schedules.forecastOnly' | transloco
      }}</mmm-inline-alert>
      @if (facade.schedules().status === 'loading') {
        <p role="status">{{ 'planning.states.loading' | transloco }}</p>
      }
      @if (facade.schedules().status === 'error') {
        <mmm-inline-alert tone="danger"
          >{{ 'planning.states.error' | transloco }}
          {{ facade.schedules().requestId }}</mmm-inline-alert
        >
      }
      <div class="entity-list">
        @for (rule of facade.schedules().data?.items ?? []; track rule.id) {
          <article class="finance-card">
            <div class="entity-heading">
              <div>
                <h2>{{ rule.title }}</h2>
                <p>
                  {{ rule.amount }} {{ rule.currency }} · {{ rule.economicType }} ·
                  {{ describe(rule.rrule) }}
                </p>
                @if (managed(rule)) {
                  <span class="data-badge">{{ 'planning.schedules.managed' | transloco }}</span>
                }
              </div>
              <div class="actions">
                <a
                  mat-button
                  [class.disabled-link]="managed(rule)"
                  [attr.aria-disabled]="managed(rule)"
                  [routerLink]="managed(rule) ? null : [rule.id, 'edit']"
                  >{{ 'state.edit' | transloco }}</a
                ><button
                  mat-button
                  type="button"
                  [disabled]="managed(rule)"
                  (click)="remove(rule.id)"
                >
                  {{ 'state.delete' | transloco }}
                </button>
              </div>
            </div>
            @if (rule.forecast; as forecast) {
              <mmm-forecast-occurrences
                [label]="rule.title"
                [forecastLabel]="'planning.schedules.forecast' | transloco"
                [disclaimer]="'planning.schedules.notPosted' | transloco"
                [truncatedLabel]="'planning.schedules.truncated' | transloco"
                [truncated]="forecast.truncated"
                [occurrences]="forecast.occurrences"
              />
            }
          </article>
        } @empty {
          <p>{{ 'planning.schedules.empty' | transloco }}</p>
        }
      </div>
    </section>
  `,
})
export class SchedulesPageComponent implements OnInit {
  protected readonly facade = inject(PlanningFacade);
  private readonly i18n = inject(TranslocoService);
  protected readonly describe = describeSupportedRRule;
  protected readonly range = new FormGroup({
    from: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(DATE_PATTERN)],
    }),
    to: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(DATE_PATTERN)],
    }),
  });
  protected readonly quotaReached = computed(() => {
    const limit = this.facade.scheduleEntitlement()?.limit;
    return (
      limit !== null &&
      limit !== undefined &&
      (this.facade.schedules().data?.items.length ?? 0) >= limit
    );
  });
  protected readonly quotaDescription = computed(() => {
    const limit = this.facade.scheduleEntitlement()?.limit;
    const used = this.facade.schedules().data?.items.length ?? 0;
    return limit === null
      ? this.i18n.translate('planning.quota.unlimited', { used })
      : this.i18n.translate('planning.quota.limited', { used, limit: limit ?? 0 });
  });
  ngOnInit(): void {
    void this.facade.loadSchedules();
  }
  protected loadForecast(): void {
    if (this.range.valid) {
      const { from, to } = this.range.getRawValue();
      void this.facade.loadSchedules(from, to);
    }
  }
  protected managed(rule: RecurringRuleResponseDto): boolean {
    return Boolean(rule.goalId || rule.loanId || rule.investmentId);
  }
  protected remove(id: string): void {
    if (globalThis.confirm(this.i18n.translate('planning.confirm.schedule')))
      void this.facade.deleteSchedule(id);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, RouterLink],
  selector: 'mmm-schedule-editor-page',
  template: `
    <section class="page-shell">
      <a routerLink="../">{{ 'planning.schedules.back' | transloco }}</a
      ><mmm-page-header
        [title]="
          editingId()
            ? ('planning.schedules.edit' | transloco)
            : ('planning.schedules.add' | transloco)
        "
        [description]="'planning.schedules.editorDescription' | transloco"
      /><mmm-command-error [facade]="facade" />
      <form class="feature-form finance-card" [formGroup]="form" (ngSubmit)="save()">
        <mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.title' | transloco }}</mat-label
          ><input matInput formControlName="title" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.amount' | transloco }}</mat-label
          ><input matInput inputmode="decimal" formControlName="amount" /></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.currency' | transloco }}</mat-label
          ><mat-select formControlName="currency">
            @for (currency of facade.currencies().data?.items ?? []; track currency.code) {
              <mat-option [value]="currency.code"
                >{{ currency.code }} — {{ currency.name }}</mat-option
              >
            }
          </mat-select></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.type' | transloco }}</mat-label
          ><mat-select formControlName="economicType"
            ><mat-option value="income">{{ 'planning.kinds.income' | transloco }}</mat-option
            ><mat-option value="expense">{{ 'planning.kinds.expense' | transloco }}</mat-option
            ><mat-option value="transfer">{{
              'planning.kinds.transfer' | transloco
            }}</mat-option></mat-select
          ></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.categoryId' | transloco }}</mat-label
          ><mat-select
            formControlName="categoryId"
            [disabled]="form.controls.economicType.value === 'transfer'"
            ><mat-option value="">—</mat-option>
            @for (category of matchingCategories(); track category.id) {
              <mat-option [value]="category.id">{{ category.label }}</mat-option>
            }
          </mat-select></mat-form-field
        ><mat-form-field appearance="outline"
          ><mat-label>{{ 'planning.fields.startsOn' | transloco }}</mat-label
          ><input matInput type="date" formControlName="startsOn"
        /></mat-form-field>
        <fieldset>
          <legend>{{ 'planning.schedules.recurrence' | transloco }}</legend>
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.frequency' | transloco }}</mat-label
            ><mat-select formControlName="frequency">
              @for (frequency of frequencies; track frequency) {
                <mat-option [value]="frequency">{{ frequency }}</mat-option>
              }
            </mat-select></mat-form-field
          ><mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.interval' | transloco }}</mat-label
            ><input matInput inputmode="numeric" formControlName="interval"
          /></mat-form-field>
          @if (form.controls.frequency.value === 'WEEKLY') {
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'planning.fields.byDay' | transloco }}</mat-label
              ><input matInput formControlName="byDay" />
              @if (form.controls.byDay.invalid) {
                <mat-error>{{ 'planning.schedules.unsupportedRule' | transloco }}</mat-error>
              }
            </mat-form-field>
          }
          @if (
            form.controls.frequency.value === 'MONTHLY' ||
            form.controls.frequency.value === 'YEARLY'
          ) {
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'planning.fields.byMonthDay' | transloco }}</mat-label
              ><input matInput formControlName="byMonthDay"
            /></mat-form-field>
          }
          @if (form.controls.frequency.value === 'YEARLY') {
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'planning.fields.byMonth' | transloco }}</mat-label
              ><input matInput formControlName="byMonth"
            /></mat-form-field>
          }
          <mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.count' | transloco }}</mat-label
            ><input matInput formControlName="count" /></mat-form-field
          ><mat-form-field appearance="outline"
            ><mat-label>{{ 'planning.fields.until' | transloco }}</mat-label
            ><input matInput type="date" formControlName="until"
          /></mat-form-field>
        </fieldset>
        <p class="data-provenance">
          {{ 'planning.schedules.rulePreview' | transloco }}: {{ rulePreview() }}
        </p>
        <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
          {{ 'state.save' | transloco }}
        </button>
      </form>
    </section>
  `,
})
export class ScheduleEditorPageComponent implements OnInit {
  protected readonly facade = inject(PlanningFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  protected readonly editingId = signal<string | null>(null);
  protected readonly frequencies: readonly SupportedFrequency[] = [
    'ONCE',
    'DAILY',
    'WEEKLY',
    'MONTHLY',
    'YEARLY',
  ];
  protected readonly form = new FormGroup({
    title: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(160)],
    }),
    amount: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(MONEY_PATTERN)],
    }),
    currency: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^[A-Z]{3}$/)],
    }),
    economicType: new FormControl<'income' | 'expense' | 'transfer'>('expense', {
      nonNullable: true,
    }),
    categoryId: new FormControl('', { nonNullable: true }),
    startsOn: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(DATE_PATTERN)],
    }),
    frequency: new FormControl<SupportedFrequency>('MONTHLY', { nonNullable: true }),
    interval: new FormControl('1', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^[1-9]\d*$/)],
    }),
    byDay: new FormControl('', {
      nonNullable: true,
      validators: [
        Validators.pattern(/^$|^(?:MO|TU|WE|TH|FR|SA|SU)(?:,(?:MO|TU|WE|TH|FR|SA|SU))*$/),
      ],
    }),
    byMonthDay: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^(?:[1-9]|[12]\d|3[01])$/)],
    }),
    byMonth: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^(?:[1-9]|1[0-2])$/)],
    }),
    count: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^[1-9]\d*$/)],
    }),
    until: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^$|^\d{4}-\d{2}-\d{2}$/)],
    }),
  });
  protected readonly matchingCategories = computed(() => {
    const type = this.form.controls.economicType.value;
    if (type === 'transfer') return [];
    return (
      this.facade
        .categories()
        .data?.items.filter(
          (category) => category.kind === (type === 'income' ? 'income' : 'spending'),
        ) ?? []
    );
  });
  ngOnInit(): void {
    void this.load();
  }
  private async load(): Promise<void> {
    await Promise.all([this.facade.loadCurrencies(), this.facade.loadCategories()]);
    const id = this.route.snapshot.paramMap.get('id');
    if (!id) return;
    this.editingId.set(id);
    await this.facade.loadSchedules();
    const rule = this.facade.schedules().data?.items.find((item) => item.id === id);
    if (!rule || rule.goalId || rule.loanId || rule.investmentId) {
      await this.router.navigateByUrl('/app/plan/schedules');
      return;
    }
    const parts = parseCanonicalRRule(rule.rrule);
    this.form.patchValue({
      title: rule.title,
      amount: rule.amount,
      currency: rule.currency,
      economicType: rule.economicType,
      categoryId: rule.categoryId ?? '',
      startsOn: rule.startsOn,
      frequency: (parts['FREQ'] as SupportedFrequency | undefined) ?? 'ONCE',
      interval: parts['INTERVAL'] ?? '1',
      byDay: parts['BYDAY'] ?? '',
      byMonthDay: parts['BYMONTHDAY'] ?? '',
      byMonth: parts['BYMONTH'] ?? '',
      count: parts['COUNT'] ?? '',
      until: parts['UNTIL'] ?? '',
    });
  }
  protected rulePreview(): string {
    const v = this.form.getRawValue();
    return buildSupportedRRule({
      frequency: v.frequency,
      interval: v.interval,
      byDay: v.byDay
        ? v.byDay
            .split(',')
            .map((d) => d.trim())
            .filter(Boolean)
        : [],
      byMonthDay: v.byMonthDay || undefined,
      byMonth: v.byMonth || undefined,
      count: v.count || undefined,
      until: v.until || undefined,
    });
  }
  protected async save(): Promise<void> {
    if (this.form.invalid) return;
    const v = this.form.getRawValue();
    const body = {
      title: v.title,
      amount: v.amount,
      currency: v.currency.toUpperCase(),
      economicType: v.economicType,
      categoryId: v.economicType === 'transfer' ? null : v.categoryId || null,
      startsOn: v.startsOn,
      rrule: this.rulePreview(),
    };
    const ok = this.editingId()
      ? await this.facade.updateSchedule(this.editingId()!, body)
      : await this.facade.createSchedule(body);
    if (ok) await this.router.navigateByUrl('/app/plan/schedules');
  }
}

type RRulePart = 'FREQ' | 'INTERVAL' | 'BYDAY' | 'BYMONTHDAY' | 'BYMONTH' | 'COUNT' | 'UNTIL';

function parseCanonicalRRule(value: string): Partial<Record<RRulePart, string>> {
  const result: Partial<Record<RRulePart, string>> = {};
  for (const part of value.split(';')) {
    const separator = part.indexOf('=');
    if (separator < 1) continue;
    const key = part.slice(0, separator);
    if (!isRRulePart(key)) continue;
    result[key] = part.slice(separator + 1);
  }
  return result;
}

function isRRulePart(value: string): value is RRulePart {
  return ['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'COUNT', 'UNTIL'].includes(value);
}
