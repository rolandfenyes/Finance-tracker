import { IsString } from 'class-validator';
import { createGlobalValidationPipe } from './validation';

class SyntheticRequest {
  @IsString()
  name!: string;
}

describe('global validation', () => {
  const pipe = createGlobalValidationPipe();

  it('rejects undeclared input fields', async () => {
    await expect(
      pipe.transform(
        { name: 'synthetic', unexpected: 'not-allowed' },
        { type: 'body', metatype: SyntheticRequest },
      ),
    ).rejects.toMatchObject({
      response: {
        code: 'VALIDATION_FAILED',
        violations: expect.arrayContaining([
          expect.objectContaining({
            field: 'unexpected',
            code: 'whitelistValidation',
          }),
        ]),
      },
    });
  });
});
