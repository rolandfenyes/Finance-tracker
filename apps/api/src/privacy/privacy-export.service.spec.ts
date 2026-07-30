import { PrivacyExportBuilder, hashIdempotencyKey } from './privacy-export.service';
import { EXPORT_DATASETS, PRIVACY_MANIFEST_VERSION } from './privacy-manifest';

describe('privacy export artifacts', () => {
  it('builds a versioned complete JSON artifact and useful quoted CSV datasets', () => {
    const generatedAt = new Date('2026-07-30T10:00:00.000Z');
    const datasets: Record<string, unknown[]> = Object.fromEntries(
      EXPORT_DATASETS.map(({ key }) => [key, []]),
    );
    datasets.profile = [
      {
        email: 'synthetic@example.test',
        full_name: 'Synthetic, "Quoted" User',
        date_of_birth: '1990-01-01',
      },
    ];
    datasets.journal_legs = [
      {
        id: 'leg-1',
        entry_id: 'entry-1',
        account_id: null,
        side: 'debit',
        amount: '12345678901234567890.123456',
        currency: 'HUF',
        created_at: generatedAt.toISOString(),
      },
    ];

    const artifacts = new PrivacyExportBuilder().build(generatedAt, datasets);
    const json = JSON.parse(
      Buffer.from(artifacts.find(({ format }) => format === 'json')!.body).toString('utf8'),
    ) as Record<string, unknown>;
    expect(json).toMatchObject({
      schema: 'mymoneymap.account-export',
      manifestVersion: PRIVACY_MANIFEST_VERSION,
      generatedAt: generatedAt.toISOString(),
    });
    expect(JSON.stringify(json)).toContain('12345678901234567890.123456');
    const csv = Buffer.from(
      artifacts.find(({ dataset }) => dataset === 'journal_legs')!.body,
    ).toString('utf8');
    expect(csv).toContain('"12345678901234567890.123456"');
    expect(artifacts.filter(({ format }) => format === 'csv')).toHaveLength(
      EXPORT_DATASETS.filter(({ csv }) => csv).length,
    );
  });

  it('validates and hashes stable idempotency keys without retaining their value', () => {
    expect(hashIdempotencyKey('export-stable-key')).toMatch(/^[0-9a-f]{64}$/);
    expect(hashIdempotencyKey('export-stable-key')).toBe(hashIdempotencyKey('export-stable-key'));
    expect(() => hashIdempotencyKey('short')).toThrow('Idempotency-Key');
  });
});
