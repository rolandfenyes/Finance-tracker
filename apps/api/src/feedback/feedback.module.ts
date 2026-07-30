import { Module } from '@nestjs/common';
import { IdentityModule } from '../identity/identity.module';
import { FeedbackController } from './feedback.controller';
import { FeedbackRepository } from './feedback.repository';
import { FeedbackService } from './feedback.service';

@Module({
  imports: [IdentityModule],
  controllers: [FeedbackController],
  providers: [FeedbackRepository, FeedbackService],
  exports: [FeedbackRepository],
})
export class FeedbackModule {}
