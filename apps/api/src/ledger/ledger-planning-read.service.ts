import { Inject, Injectable } from '@nestjs/common';
import { LedgerRepository } from './ledger.repository';

@Injectable()
export class LedgerPlanningReadService {
  constructor(@Inject(LedgerRepository) private readonly repository: LedgerRepository) {}

  spendingByCategories(
    userId: string,
    categoryIds: readonly string[],
    first: string,
    last: string,
  ): ReturnType<LedgerRepository['spendingByCategories']> {
    return this.repository.spendingByCategories(userId, categoryIds, first, last);
  }
}
