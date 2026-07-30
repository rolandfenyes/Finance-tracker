import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { environmentFileFor, validateEnvironment } from './platform/config/environment';
import { DatabaseModule } from './platform/database/database.module';
import { HealthModule } from './platform/health/health.module';
import { IdempotencyModule } from './platform/idempotency/idempotency.module';
import { PlatformLoggingModule } from './platform/logging/logging.module';
import { IdentityModule } from './identity/identity.module';
import { UsersModule } from './users/users.module';
import { LedgerModule } from './ledger/ledger.module';
import { CurrencyModule } from './currency/currency.module';
import { BudgetingModule } from './budgeting/budgeting.module';
import { RecurrenceModule } from './recurrence/recurrence.module';
import { ReportingModule } from './reporting/reporting.module';
import { GoalsModule } from './goals/goals.module';
import { EmergencyReserveModule } from './emergency-reserve/emergency-reserve.module';
import { LoansModule } from './loans/loans.module';
import { InvestmentsModule } from './investments/investments.module';
import { SecuritiesModule } from './securities/securities.module';
import { FeedbackModule } from './feedback/feedback.module';
import { AdministrationModule } from './administration/administration.module';

@Module({
  imports: [
    ConfigModule.forRoot({
      cache: true,
      envFilePath: environmentFileFor(process.env.NODE_ENV),
      ignoreEnvFile: process.env.NODE_ENV === 'production',
      isGlobal: true,
      validate: validateEnvironment,
    }),
    PlatformLoggingModule,
    DatabaseModule,
    IdempotencyModule,
    IdentityModule,
    UsersModule,
    CurrencyModule,
    LedgerModule,
    BudgetingModule,
    RecurrenceModule,
    ReportingModule,
    GoalsModule,
    EmergencyReserveModule,
    LoansModule,
    InvestmentsModule,
    SecuritiesModule,
    FeedbackModule,
    AdministrationModule,
    HealthModule,
  ],
})
export class AppModule {}
