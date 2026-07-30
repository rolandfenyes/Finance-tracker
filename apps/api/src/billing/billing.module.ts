import { Module } from '@nestjs/common';
import { AdminGuard } from '../administration/admin.guard';
import { IdentityModule } from '../identity/identity.module';
import { BillingController } from './billing.controller';
import { BillingService } from './billing.service';

@Module({
  imports: [IdentityModule],
  controllers: [BillingController],
  providers: [BillingService, AdminGuard],
})
export class BillingModule {}
