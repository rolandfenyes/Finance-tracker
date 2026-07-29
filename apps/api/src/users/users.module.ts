import { Module } from '@nestjs/common';
import { IdentityModule } from '../identity/identity.module';
import { TimeModule } from '../platform/time/time.module';
import { EntitlementsService, PersonalFinanceAccessGuard } from './entitlements.service';
import { UsersController } from './users.controller';
import { UsersRepository } from './users.repository';
import { UsersService } from './users.service';

@Module({
  imports: [IdentityModule, TimeModule],
  controllers: [UsersController],
  providers: [UsersRepository, UsersService, EntitlementsService, PersonalFinanceAccessGuard],
  exports: [UsersService, EntitlementsService, PersonalFinanceAccessGuard],
})
export class UsersModule {}
