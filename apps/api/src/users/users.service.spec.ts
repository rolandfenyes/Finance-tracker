import type { UserSettingsRecord } from './users.types';
import { mapOnboarding } from './users.service';

const base: UserSettingsRecord = {
  id: '00000000-0000-4000-8000-000000000001',
  email: 'synthetic@example.test',
  fullName: 'Synthetic User',
  dateOfBirth: '1990-01-01',
  role: 'free',
  emailVerified: true,
  theme: 'verdant-horizon',
  desiredLanguage: 'en',
  onboardStep: 0,
  needsTutorial: true,
  tutorialSeen: false,
  createdAt: new Date('2026-07-29T10:00:00.000Z'),
  updatedAt: new Date('2026-07-29T10:00:00.000Z'),
};

describe('onboarding state mapping', () => {
  it.each([
    [0, 'theme'],
    [1, 'theme'],
    [2, 'rules'],
    [3, 'currencies'],
    [4, 'categories'],
    [5, 'income'],
  ] as const)('maps persisted step %s to %s', (onboardStep, next) => {
    expect(mapOnboarding({ ...base, onboardStep })).toMatchObject({
      currentStep: onboardStep,
      next,
      onboardingComplete: false,
      tutorialRequired: true,
      tutorialCompleted: false,
    });
  });

  it('moves completed onboarding through tutorial to complete', () => {
    expect(mapOnboarding({ ...base, onboardStep: 6 }).next).toBe('tutorial');
    expect(
      mapOnboarding({
        ...base,
        onboardStep: 6,
        needsTutorial: false,
        tutorialSeen: true,
      }),
    ).toEqual({
      currentStep: 6,
      next: 'complete',
      onboardingComplete: true,
      tutorialRequired: false,
      tutorialCompleted: true,
    });
  });
});
