import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import { NoResultError } from 'kysely';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { ApplicationError } from '../platform/http/application-error';
import { CLOCK, type Clock } from '../platform/time/clock';
import { EntitlementsService } from '../users/entitlements.service';
import type { CurrencyCatalogueResponseDto, UserCurrenciesResponseDto } from './currency.dto';
import { CurrencyRepository } from './currency.repository';
import { FxRefreshQueueService } from './fx-refresh-queue.service';

@Injectable()
export class CurrencyService {
  constructor(
    @Inject(CurrencyRepository) private readonly repository: CurrencyRepository,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(FxRefreshQueueService) private readonly refreshQueue: FxRefreshQueueService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async catalogue(): Promise<CurrencyCatalogueResponseDto> {
    return { items: await this.repository.catalogue() };
  }

  async userCurrencies(userId: string): Promise<UserCurrenciesResponseDto> {
    const [items, catalogue] = await Promise.all([
      this.repository.userCurrencies(userId),
      this.repository.catalogue(),
    ]);
    const main = items.find((currency) => currency.isMain);
    if (!main) throw new ApplicationError(409, 'CONFLICT', 'Main currency is not configured');
    const selected = new Set(items.map(({ code }) => code));
    return {
      mainCurrency: main.code,
      items,
      available: catalogue.filter(({ code }) => !selected.has(code)),
    };
  }

  async add(userId: string, code: string): Promise<UserCurrenciesResponseDto> {
    this.assertCode(code);
    let added = false;
    try {
      await this.repository.transaction(async (transaction) => {
        const role = await this.repository.lockFinanceUser(transaction, userId);
        if (!role) throw forbidden();
        if (!(await this.repository.currency(code, transaction))) throw unsupported();
        const currentCount = await this.repository.countUserCurrencies(transaction, userId);
        if (
          await transaction
            .selectFrom('mymoneymap.user_currencies')
            .select('code')
            .where('user_id', '=', userId)
            .where('code', '=', code)
            .executeTakeFirst()
        ) {
          return;
        }
        this.entitlements.assertWithinQuota(role, 'currencies', currentCount);
        added = await this.repository.addUserCurrency(
          transaction,
          userId,
          code,
          this.clock.now().toDate(),
        );
      });
    } catch (error) {
      throw translate(error);
    }
    if (added) await this.refreshQueue.enqueue(code, this.clock.now().toString().slice(0, 10));
    return this.userCurrencies(userId);
  }

  async remove(userId: string, code: string): Promise<void> {
    this.assertCode(code);
    try {
      const removed = await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.lockFinanceUser(transaction, userId))) throw forbidden();
        return this.repository.removeUserCurrency(transaction, userId, code);
      });
      if (!removed) {
        throw new ApplicationError(
          HttpStatus.CONFLICT,
          'CONFLICT',
          'Main currency cannot be removed or membership was not found',
        );
      }
    } catch (error) {
      throw translate(error);
    }
  }

  async setMain(userId: string, code: string): Promise<UserCurrenciesResponseDto> {
    this.assertCode(code);
    try {
      const found = await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.lockFinanceUser(transaction, userId))) throw forbidden();
        return this.repository.setMainCurrency(transaction, userId, code);
      });
      if (!found) throw new ApplicationError(404, 'NOT_FOUND', 'Currency membership was not found');
      return this.userCurrencies(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  private assertCode(code: string): void {
    try {
      CurrencyCode.create(code);
    } catch {
      throw unsupported();
    }
  }
}

function unsupported(): ApplicationError {
  return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', 'Currency is not supported');
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function translate(error: unknown): Error {
  if (error instanceof ApplicationError) return error;
  if (error instanceof NoResultError)
    return new ApplicationError(404, 'NOT_FOUND', 'User not found');
  const code =
    typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
  if (code === '23505') return new ApplicationError(409, 'CONFLICT', 'Currency update conflicted');
  if (code === '23503' || code === '23514') {
    return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', 'Currency invariant failed');
  }
  return error instanceof Error ? error : new Error('Currency persistence failed');
}
