import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { IdentityModule } from '../identity/identity.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import {
  DisabledPrivateObjectStorage,
  PRIVATE_OBJECT_STORAGE,
  type PrivateObjectStorage,
  S3PrivateObjectStorage,
} from './private-object-storage';
import { PrivacyCleanupService } from './privacy-cleanup.service';
import { PrivacyController } from './privacy.controller';
import { PrivacyDeletionProcessor, PrivacyDeletionService } from './privacy-deletion.service';
import {
  PrivacyExportBuilder,
  PrivacyExportProcessor,
  PrivacyExportService,
} from './privacy-export.service';
import { PrivacyQueueService } from './privacy-queue.service';
import { PrivacyRepository } from './privacy.repository';

@Module({
  imports: [IdentityModule, TimeModule, UsersModule],
  controllers: [PrivacyController],
  providers: [
    PrivacyRepository,
    PrivacyExportBuilder,
    PrivacyExportService,
    PrivacyExportProcessor,
    PrivacyDeletionService,
    PrivacyDeletionProcessor,
    PrivacyCleanupService,
    PrivacyQueueService,
    {
      provide: PRIVATE_OBJECT_STORAGE,
      inject: [ConfigService],
      useFactory: (config: ConfigService): PrivateObjectStorage =>
        config.getOrThrow<string>('PRIVACY_EXPORT_STORAGE_PROVIDER') === 's3'
          ? new S3PrivateObjectStorage(config)
          : new DisabledPrivateObjectStorage(),
    },
  ],
  exports: [PrivacyQueueService],
})
export class PrivacyModule {}
