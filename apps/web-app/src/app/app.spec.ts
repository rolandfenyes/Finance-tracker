import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideAppI18n } from '@mymoneymap/web-shared';
import { App } from './app';

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideRouter([]), provideAppI18n()],
    }).compileComponents();
  });

  it('bootstraps a localized skip link and router outlet', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector<HTMLAnchorElement>('.skip-link')?.href).toContain(
      '#main-content',
    );
    expect(compiled.querySelector('.skip-link')?.textContent?.trim()).toBe('Skip to main content');
    expect(compiled.querySelector('router-outlet')).not.toBeNull();
  });
});
