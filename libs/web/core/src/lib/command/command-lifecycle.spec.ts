import { CommandLifecycle, type IdempotencyKeyFactory } from './command-lifecycle';

describe('CommandLifecycle', () => {
  it('retains a key across retries of one intent and rotates for a new intent', () => {
    const keys = ['key-one', 'key-two'];
    const factory: IdempotencyKeyFactory = { create: () => keys.shift() ?? 'unexpected' };
    const command = new CommandLifecycle<string>(factory);

    expect(command.begin('intent-one')).toBe('key-one');
    command.fail(new Error('synthetic'));
    expect(command.begin('intent-one')).toBe('key-one');
    command.succeed('saved');
    expect(command.state()).toMatchObject({ phase: 'succeeded', result: 'saved' });
    expect(command.begin('intent-two')).toBe('key-two');
    command.reset();
    expect(command.state()).toEqual({ phase: 'idle' });
  });

  it('retains the key while an uncertain result is reconciled', () => {
    const command = new CommandLifecycle<string>({ create: (): string => 'stable-key' });
    command.begin('same-intent');
    command.uncertain(new Error('connection closed'));

    expect(command.state()).toMatchObject({
      phase: 'uncertain',
      intentId: 'same-intent',
      idempotencyKey: 'stable-key',
    });
    expect(command.begin('same-intent')).toBe('stable-key');
  });
});
