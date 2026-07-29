import { promises as fs } from 'node:fs';
import path from 'node:path';

describe('PostgreSQL least-privilege bootstrap contract', () => {
  it('keeps runtime DML-only and migration metadata inaccessible', async () => {
    const sql = await fs.readFile(
      path.resolve(process.cwd(), 'apps/api/database/bootstrap/roles.sql'),
      'utf8',
    );

    expect(sql).toContain('mymoneymap_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE');
    expect(sql).toContain('mymoneymap_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE');
    expect(sql).toContain('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
    expect(sql).toContain('REVOKE ALL ON SCHEMA mymoneymap_meta FROM mymoneymap_runtime');
    expect(sql).toContain(
      'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA mymoneymap',
    );
    expect(sql).toContain('ALTER DEFAULT PRIVILEGES FOR ROLE mymoneymap_migrator');
    expect(sql).not.toMatch(/CREATE ROLE\s+\S+\s+SUPERUSER/i);
    expect(sql).not.toMatch(/\bPASSWORD\s+'/i);
    expect(sql).not.toMatch(/default[_ -]?admin/i);
  });
});
