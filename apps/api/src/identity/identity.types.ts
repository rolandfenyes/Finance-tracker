export type UserRole = 'free' | 'premium' | 'admin';
export type UserStatus = 'active' | 'inactive';

export interface AuthenticatedPrincipal {
  userId: string;
  role: UserRole;
  emailVerified: boolean;
}

export interface IdentityUser {
  id: string;
  email: string;
  passwordHash: string;
  fullName: string;
  dateOfBirth: string;
  role: UserRole;
  status: UserStatus;
  emailVerifiedAt: Date | null;
  createdAt: Date;
}
