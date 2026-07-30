import { Inject, Injectable, Logger } from '@nestjs/common';
import { ApplicationError } from '../platform/http/application-error';
import { CLOCK, type Clock } from '../platform/time/clock';
import { PasswordService } from '../identity/password.service';
import { PrivacyCleanupService } from './privacy-cleanup.service';
import { hashIdempotencyKey } from './privacy-export.service';
import { type DeletionRequestRow, PrivacyRepository } from './privacy.repository';

@Injectable()
export class PrivacyDeletionService {
  constructor(
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PasswordService) private readonly passwords: PasswordService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async prepare(
    userId: string,
    input: { confirmEmail: string; password: string },
    idempotencyKey: string,
  ): Promise<DeletionRequestRow> {
    const user = await this.repository.userForReauthentication(userId);
    if (!user) throw new ApplicationError(401, 'UNAUTHORIZED', 'Authentication is required');
    if (user.role === 'admin') {
      throw new ApplicationError(
        403,
        'FORBIDDEN',
        'Administrator accounts require a separately audited offboarding process',
      );
    }
    if (input.confirmEmail.trim().toLowerCase() !== user.email.toLowerCase()) {
      throw invalidConfirmation();
    }
    if (!(await this.passwords.verify(user.password_hash, input.password))) {
      throw invalidConfirmation();
    }
    return this.repository.createDeletionRequest({
      userId,
      idempotencyKeyHash: hashIdempotencyKey(idempotencyKey),
      now: this.clock.now().toDate(),
    });
  }
}

@Injectable()
export class PrivacyDeletionProcessor {
  private readonly logger = new Logger(PrivacyDeletionProcessor.name);

  constructor(
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PrivacyCleanupService) private readonly cleanup: PrivacyCleanupService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async process(requestId: string, attempt: number): Promise<void> {
    const request = await this.repository.claimDeletion(
      requestId,
      attempt,
      this.clock.now().toDate(),
    );
    if (!request?.user_id) return;
    try {
      await this.cleanup.cleanup(request.user_id);
      await this.repository.deleteAccountDataAndComplete(
        request.user_id,
        request.id,
        request.subject_hash,
        this.clock.now().toDate(),
      );
      this.logger.log({ requestId, status: 'completed' }, 'Privacy deletion state changed');
    } catch (error) {
      const errorCode = deletionErrorCode(error);
      await this.repository.failDeletion(
        request.id,
        request.subject_hash,
        attempt,
        errorCode,
        this.clock.now().toDate(),
      );
      this.logger.warn(
        {
          requestId,
          status: attempt >= 3 ? 'dead_letter' : 'retryable_failed',
          errorCode,
          attempt,
        },
        'Privacy deletion state changed',
      );
      throw error;
    }
  }
}

function invalidConfirmation(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Account deletion confirmation was incorrect');
}

function deletionErrorCode(error: unknown): string {
  if (error instanceof Error && error.message.includes('job')) return 'queue_cleanup_failed';
  if (error instanceof Error && error.message.includes('storage')) return 'storage_cleanup_failed';
  if (error instanceof Error && error.message.includes('redis')) return 'cache_cleanup_failed';
  return 'account_deletion_failed';
}
