import { computed, Injectable, signal } from '@angular/core';
import type { CurrentUserResponseDto } from '@mymoneymap/generated-api-client/models/current-user-response-dto';

export type SessionStatus = 'unknown' | 'loading' | 'authenticated' | 'anonymous' | 'unavailable';

@Injectable({ providedIn: 'root' })
export class SessionState {
  private readonly statusSignal = signal<SessionStatus>('unknown');
  private readonly userSignal = signal<CurrentUserResponseDto | null>(null);

  readonly status = this.statusSignal.asReadonly();
  readonly currentUser = this.userSignal.asReadonly();
  readonly authenticated = computed(() => this.statusSignal() === 'authenticated');

  markLoading(): void {
    this.statusSignal.set('loading');
  }

  markAuthenticated(user: CurrentUserResponseDto): void {
    this.userSignal.set(user);
    this.statusSignal.set('authenticated');
  }

  markAnonymous(): void {
    this.userSignal.set(null);
    this.statusSignal.set('anonymous');
  }

  markUnavailable(): void {
    this.userSignal.set(null);
    this.statusSignal.set('unavailable');
  }
}
