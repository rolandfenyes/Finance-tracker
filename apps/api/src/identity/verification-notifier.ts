export const VERIFICATION_NOTIFIER = Symbol('VERIFICATION_NOTIFIER');

export interface VerificationNotifier {
  sendVerification(input: { email: string; fullName: string; token: string }): Promise<void>;
}

export class DeferredVerificationNotifier implements VerificationNotifier {
  sendVerification(): Promise<void> {
    // Email delivery is owned by Step 18. Step 04 persists secure token state and exposes this port.
    return Promise.resolve();
  }
}
