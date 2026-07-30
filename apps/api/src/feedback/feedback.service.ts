/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { FeedbackRepository } from './feedback.repository';
import type {
  CreateFeedbackDto,
  FeedbackListQueryDto,
  UpdateOwnedFeedbackStatusDto,
} from './feedback.dto';

@Injectable()
export class FeedbackService {
  constructor(@Inject(FeedbackRepository) private readonly repository: FeedbackRepository) {}

  list(userId: string, query: FeedbackListQueryDto) {
    return this.repository.listOwned({ userId, ...query });
  }

  create(userId: string, dto: CreateFeedbackDto) {
    return this.repository.create({
      userId,
      kind: dto.kind,
      title: dto.title,
      message: dto.message,
      severity: dto.severity ?? null,
      now: new Date(),
    });
  }

  updateStatus(userId: string, id: string, dto: UpdateOwnedFeedbackStatusDto) {
    return this.repository.updateOwnedStatus(userId, id, dto.status, new Date());
  }

  delete(userId: string, id: string): Promise<void> {
    return this.repository.deleteOwned(userId, id);
  }
}
