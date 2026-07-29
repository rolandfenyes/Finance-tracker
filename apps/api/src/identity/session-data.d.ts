import 'express-session';

declare module 'express-session' {
  interface SessionData {
    principal?: {
      userId: string;
      role: 'free' | 'premium' | 'admin';
      emailVerified: boolean;
    };
    authenticatedAt?: string;
    absoluteExpiresAt?: string;
    webauthn?: {
      challenge: string;
      expiresAt: string;
      flow: 'authentication' | 'registration';
      userId?: string;
    };
  }
}
