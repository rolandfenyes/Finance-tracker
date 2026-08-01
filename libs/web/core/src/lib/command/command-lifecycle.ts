import { Injectable, signal } from '@angular/core';

export type CommandState<T> =
  | { readonly phase: 'idle' }
  | { readonly phase: 'submitting'; readonly intentId: string; readonly idempotencyKey: string }
  | {
      readonly phase: 'succeeded';
      readonly intentId: string;
      readonly idempotencyKey: string;
      readonly result: T;
    }
  | {
      readonly phase: 'failed';
      readonly intentId: string;
      readonly idempotencyKey: string;
      readonly error: unknown;
    }
  | {
      readonly phase: 'uncertain';
      readonly intentId: string;
      readonly idempotencyKey: string;
      readonly error: unknown;
    };

export interface IdempotencyKeyFactory {
  create(): string;
}

@Injectable({ providedIn: 'root' })
export class BrowserIdempotencyKeyFactory implements IdempotencyKeyFactory {
  create(): string {
    return crypto.randomUUID();
  }
}

export class CommandLifecycle<T> {
  private readonly stateSignal = signal<CommandState<T>>({ phase: 'idle' });
  private retainedIntent: { intentId: string; key: string } | null = null;

  readonly state = this.stateSignal.asReadonly();

  constructor(private readonly keyFactory: IdempotencyKeyFactory) {}

  begin(intentId: string): string {
    if (intentId.trim().length === 0) throw new Error('Command intent must not be empty');
    const key =
      this.retainedIntent?.intentId === intentId
        ? this.retainedIntent.key
        : this.keyFactory.create();
    this.retainedIntent = { intentId, key };
    this.stateSignal.set({ phase: 'submitting', intentId, idempotencyKey: key });
    return key;
  }

  succeed(result: T): void {
    const active = this.requireActive();
    this.stateSignal.set({
      phase: 'succeeded',
      intentId: active.intentId,
      idempotencyKey: active.key,
      result,
    });
  }

  fail(error: unknown): void {
    const active = this.requireActive();
    this.stateSignal.set({
      phase: 'failed',
      intentId: active.intentId,
      idempotencyKey: active.key,
      error,
    });
  }

  uncertain(error: unknown): void {
    const active = this.requireActive();
    this.stateSignal.set({
      phase: 'uncertain',
      intentId: active.intentId,
      idempotencyKey: active.key,
      error,
    });
  }

  reset(): void {
    this.retainedIntent = null;
    this.stateSignal.set({ phase: 'idle' });
  }

  private requireActive(): { intentId: string; key: string } {
    if (!this.retainedIntent) throw new Error('No command intent is active');
    return this.retainedIntent;
  }
}
