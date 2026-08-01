import { TestBed } from '@angular/core/testing';
import type { FormGroup } from '@angular/forms';
import { OnboardingFacade } from './onboarding.facade';
import { IncomeStepComponent } from './onboarding-pages';

describe('IncomeStepComponent', () => {
  it('loads owned currencies and defaults to the server-returned main currency', async () => {
    const facade = {
      loadCurrencies: vi.fn().mockResolvedValue(undefined),
      createIncome: vi.fn().mockResolvedValue(undefined),
      currencies: vi.fn(() => ({
        mainCurrency: 'AUD',
        items: [
          {
            code: 'AUD',
            name: 'Australian Dollar',
            minorUnit: 2,
            roundingMode: 'HALF_EVEN',
            isMain: true,
          },
        ],
        available: [],
      })),
    };
    TestBed.configureTestingModule({
      providers: [{ provide: OnboardingFacade, useValue: facade }],
    });
    const component = TestBed.runInInjectionContext(() => new IncomeStepComponent());

    component.ngOnInit();

    await vi.waitFor(() => {
      expect(facade.loadCurrencies).toHaveBeenCalledOnce();
      expect(incomeForm(component).controls['currency']?.value).toBe('AUD');
    });

    incomeForm(component).setValue({
      label: 'Synthetic salary',
      amount: '2527',
      currency: 'AUD',
      validFrom: '2026-07-01',
    });
    submitIncome(component);

    expect(facade.createIncome).toHaveBeenCalledWith({
      label: 'Synthetic salary',
      amount: '2527',
      currency: 'AUD',
      validFrom: '2026-07-01',
    });
  });
});

function incomeForm(component: IncomeStepComponent): FormGroup {
  return (component as unknown as { form: FormGroup }).form;
}

function submitIncome(component: IncomeStepComponent): void {
  (component as unknown as { submit: () => void }).submit();
}
