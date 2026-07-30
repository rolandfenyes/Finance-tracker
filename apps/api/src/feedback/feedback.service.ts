/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable, Optional } from '@nestjs/common';
import { FeedbackRepository } from './feedback.repository';
import {
  NOTIFICATION_TRIGGER,
  type NotificationTrigger,
} from '../notifications/notification-trigger.port';
import type {
  CreateFeedbackDto,
  FeedbackListQueryDto,
  UpdateOwnedFeedbackStatusDto,
} from './feedback.dto';

@Injectable()
export class FeedbackService {
  constructor(
    @Inject(FeedbackRepository) private readonly repository: FeedbackRepository,
    @Optional()
    @Inject(NOTIFICATION_TRIGGER)
    private readonly notificationTrigger?: NotificationTrigger,
  ) {}

  list(userId: string, query: FeedbackListQueryDto) {
    return this.repository.listOwned({ userId, ...query });
  }

  async create(userId: string, dto: CreateFeedbackDto) {
    const feedback = await this.repository.create({
      userId,
      kind: dto.kind,
      title: dto.title,
      message: dto.message,
      severity: dto.severity ?? null,
      now: new Date(),
    });
    await this.notificationTrigger?.feedbackCreated({
      feedbackId: feedback.id,
      userId,
      title: feedback.title,
      kind: feedback.kind,
      severity: feedback.severity,
      createdAt: feedback.createdAt,
    });
    return feedback;
  }

  updateStatus(userId: string, id: string, dto: UpdateOwnedFeedbackStatusDto) {
    return this.repository.updateOwnedStatus(userId, id, dto.status, new Date());
  }

  delete(userId: string, id: string): Promise<void> {
    return this.repository.deleteOwned(userId, id);
  }
}
