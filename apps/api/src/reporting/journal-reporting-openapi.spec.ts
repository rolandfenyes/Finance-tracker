import { readFileSync } from 'node:fs';

interface Schema {
  $ref?: string;
  allOf?: Schema[];
  type?: string;
  format?: string;
  enum?: string[];
  nullable?: boolean;
  properties?: Record<string, Schema>;
  required?: string[];
}

interface Contract {
  components: { schemas: Record<string, Schema> };
}

describe('journal and reporting OpenAPI contracts', () => {
  const contract = JSON.parse(readFileSync('apps/api/openapi/openapi.json', 'utf8')) as Contract;

  it('publishes the complete reusable journal conversion snapshot schema', () => {
    expect(contract.components.schemas.JournalEntryResponseDto?.properties?.conversion).toEqual({
      description:
        'Immutable main-currency conversion snapshot. convertedAmount is absent when status is unavailable.',
      allOf: [{ $ref: '#/components/schemas/JournalConversionResponseDto' }],
    });

    const conversion = contract.components.schemas.JournalConversionResponseDto;
    expect(Object.keys(conversion?.properties ?? {})).toEqual(
      expect.arrayContaining([
        'status',
        'sourceAmount',
        'sourceCurrency',
        'targetCurrency',
        'convertedAmount',
        'sourceRate',
        'targetRate',
        'conversionRate',
        'provider',
        'rateAt',
        'fetchedAt',
        'precision',
        'roundingMode',
      ]),
    );
    expect(conversion?.required).toEqual([
      'status',
      'sourceAmount',
      'sourceCurrency',
      'targetCurrency',
      'precision',
      'roundingMode',
    ]);
    expect(conversion?.properties?.status?.enum).toEqual(['available', 'stale', 'unavailable']);
    expect(conversion?.properties?.roundingMode?.enum).toEqual([
      'DOWN',
      'UP',
      'HALF_UP',
      'HALF_EVEN',
    ]);
    expect(conversion?.properties?.rateAt).toMatchObject({
      type: 'string',
      format: 'date-time',
    });
  });

  it('reuses the typed ledger source provenance schema for report activity', () => {
    expect(contract.components.schemas.ReportActivityItemDto?.properties?.source).toEqual({
      $ref: '#/components/schemas/JournalSourceResponseDto',
    });

    const source = contract.components.schemas.JournalSourceResponseDto;
    expect(source?.required).toEqual(['module', 'referenceId']);
    expect(source?.properties?.module?.enum).toEqual([
      'manual',
      'scheduling',
      'goals',
      'emergency_fund',
      'loans',
      'investments',
      'securities',
      'migration',
    ]);
    expect(source?.properties?.referenceId).toMatchObject({
      type: 'string',
      format: 'uuid',
      nullable: true,
    });
  });
});
