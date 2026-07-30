export type EmergencyReserveMovementDirection = 'contribution' | 'withdrawal';

export interface EmergencyReserveMovement {
  id: string;
  journalEntryId: string;
  holdingAccountId: string;
  direction: EmergencyReserveMovementDirection;
  amount: string;
  currency: string;
  reserveAmount: string;
  reserveCurrency: string;
  occurredOn: string;
  note: string | null;
  reversedByJournalEntryId: string | null;
  createdAt: string;
}

export interface ScheduledActivityTotal {
  currency: string;
  income: string;
  expense: string;
  transfer: string;
}

export interface EmergencyReserve {
  configured: boolean;
  targetAmount: string;
  currentAmount: string;
  currency: string;
  reserveAccountId: string | null;
  linkedInvestmentAccountId: string | null;
  targetMethodology: {
    code: 'manual_user_defined';
    label: 'User-defined reserve target';
    educationalOnly: true;
  };
  scheduledActivity: {
    classification: 'raw_unclassified_scheduled_activity';
    label: 'Raw scheduled activity totals';
    periodFrom: string;
    periodTo: string;
    totals: ScheduledActivityTotal[];
  };
  movements: EmergencyReserveMovement[];
  createdAt: string | null;
  updatedAt: string | null;
}

export interface LockedEmergencyReserve {
  userId: string;
  targetAmount: string;
  currency: string;
  reserveAccountId: string;
  linkedInvestmentAccountId: string | null;
  holdingAccountId: string;
  currentAmount: string;
  createdAt: Date;
  updatedAt: Date;
}
