/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { SecuritiesController } from './securities.controller';
import { SecuritiesRepository } from './securities.repository';
import { SecuritiesService } from './securities.service';
import { DisabledMarketDataProvider } from './disabled-market-data.provider';
import { FinnhubMarketDataProvider } from './finnhub-market-data.provider';
import {
  SecuritiesRefreshProcessor,
  SecuritiesRefreshQueueService,
} from './securities-refresh-queue.service';
import { SECURITIES_MARKET_DATA_PROVIDER } from './securities.types';

@Module({
  imports: [IdentityModule, UsersModule, CurrencyModule, LedgerModule, TimeModule],
  controllers: [SecuritiesController],
  providers: [
    SecuritiesRepository,
    SecuritiesService,
    DisabledMarketDataProvider,
    FinnhubMarketDataProvider,
    SecuritiesRefreshProcessor,
    SecuritiesRefreshQueueService,
    {
      provide: SECURITIES_MARKET_DATA_PROVIDER,
      inject: [ConfigService, DisabledMarketDataProvider, FinnhubMarketDataProvider],
      useFactory: (
        config: ConfigService,
        disabled: DisabledMarketDataProvider,
        finnhub: FinnhubMarketDataProvider,
      ) =>
        config.get<boolean>('SECURITIES_MARKET_DATA_ENABLED') &&
        config.get<string>('SECURITIES_PROVIDER') === 'finnhub'
          ? finnhub
          : disabled,
    },
  ],
})
export class SecuritiesModule {}
