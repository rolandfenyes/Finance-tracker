import { Module } from '@nestjs/common';
import { IdentityModule } from '../identity/identity.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { CurrencyController } from './currency.controller';
import { CurrencyRepository } from './currency.repository';
import { CurrencyService } from './currency.service';
import { FrankfurterFxProvider } from './frankfurter-fx.provider';
import { FxConversionService } from './fx-conversion.service';
import { FxRefreshProcessor, FxRefreshQueueService } from './fx-refresh-queue.service';

@Module({
  imports: [IdentityModule, TimeModule, UsersModule],
  controllers: [CurrencyController],
  providers: [
    CurrencyRepository,
    CurrencyService,
    FxConversionService,
    FrankfurterFxProvider,
    FxRefreshProcessor,
    FxRefreshQueueService,
  ],
  exports: [CurrencyRepository, CurrencyService, FxConversionService, FxRefreshQueueService],
})
export class CurrencyModule {}
