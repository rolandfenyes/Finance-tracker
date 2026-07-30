export interface RecoveryNotification {
  email: string;
  fullName: string;
  token: string;
}

export interface EmailChangeNotification extends RecoveryNotification {
  pendingEmail: string;
}

export interface RecoveryNotifier {
  sendPasswordReset(notification: RecoveryNotification): Promise<void>;
  sendEmailVerification(notification: RecoveryNotification): Promise<void>;
  sendEmailChange(notification: EmailChangeNotification): Promise<void>;
}

export const RECOVERY_NOTIFIER = Symbol('RECOVERY_NOTIFIER');

export class DeferredRecoveryNotifier implements RecoveryNotifier {
  sendPasswordReset(notification: RecoveryNotification): Promise<void> {
    void notification;
    return Promise.resolve();
  }

  sendEmailVerification(notification: RecoveryNotification): Promise<void> {
    void notification;
    return Promise.resolve();
  }

  sendEmailChange(notification: EmailChangeNotification): Promise<void> {
    void notification;
    return Promise.resolve();
  }
}
