import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import type { LedgerEconomicType } from '../ledger/ledger.types';
import { ApplicationError } from '../platform/http/application-error';
import { BudgetingRepository } from './budgeting.repository';

@Injectable()
export class CategoryPolicyService {
  constructor(@Inject(BudgetingRepository) private readonly repository: BudgetingRepository) {}

  async assertJournalCategory(
    userId: string,
    categoryId: string,
    economicType: LedgerEconomicType,
  ): Promise<void> {
    const category = await this.repository.category(userId, categoryId);
    if (!category) {
      throw new ApplicationError(HttpStatus.NOT_FOUND, 'NOT_FOUND', 'Category was not found');
    }
    const expected =
      economicType === 'external_income'
        ? 'income'
        : economicType === 'external_expense' || economicType === 'fee'
          ? 'spending'
          : null;
    if (expected === null || category.kind !== expected) {
      throw new ApplicationError(
        HttpStatus.UNPROCESSABLE_ENTITY,
        'UNPROCESSABLE_ENTITY',
        'Category kind does not match the journal economic type',
      );
    }
  }
}
