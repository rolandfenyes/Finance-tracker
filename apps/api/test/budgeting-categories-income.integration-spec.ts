import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('budgeting, categories, and basic income PostgreSQL invariants', () => {
  it('migrates Step 08 up and rolls it back without disturbing Step 07', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'budget_rules')).toBe('mymoneymap.budget_rules');
      expect(await relation(pool, 'categories')).toBe('mymoneymap.categories');
      expect(await relation(pool, 'basic_incomes')).toBe('mymoneymap.basic_incomes');

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'budget_rules')).toBe('mymoneymap.budget_rules');

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'budget_rules')).toBe('mymoneymap.budget_rules');

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBeNull();
      expect(await relation(pool, 'budget_rules')).toBe('mymoneymap.budget_rules');

      await migrateOneDown(database);
      expect(await relation(pool, 'budget_rules')).toBeNull();
      expect(await relation(pool, 'categories')).toBeNull();
      expect(await relation(pool, 'basic_incomes')).toBeNull();
      expect(await relation(pool, 'fx_conversion_snapshots')).toBe(
        'mymoneymap.fx_conversion_snapshots',
      );

      await migrateToLatest(database);
      expect(await relation(pool, 'categories')).toBe('mymoneymap.categories');
    });
  });

  it('enforces exact percentages, color, ownership, category kind, currency, and date constraints', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userA = await insertUser(pool);
      const userB = await insertUser(pool);
      const ruleA = await insertRule(pool, userA, '100.0000');
      const ruleB = await insertRule(pool, userB, '50');
      const incomeA = await insertCategory(pool, userA, 'income');
      const spendingA = await insertCategory(pool, userA, 'spending');

      await expect(insertRule(pool, userA, '100.0001')).rejects.toMatchObject({
        code: '23514',
      });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.categories
            (id,user_id,label,kind,color,created_at,updated_at)
           VALUES ($1,$2,'Bad','spending','red',now(),now())`,
          [randomUUID(), userA],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query(
          `UPDATE mymoneymap.categories
              SET budget_rule_id = $1
            WHERE id = $2`,
          [ruleB, spendingA],
        ),
      ).rejects.toMatchObject({ code: '23503' });
      await expect(
        pool.query(
          `UPDATE mymoneymap.categories
              SET budget_rule_id = $1
            WHERE id = $2`,
          [ruleA, incomeA],
        ),
      ).rejects.toMatchObject({ code: '23514' });

      await expect(
        insertIncome(pool, userA, spendingA, '10.123456789012', 'HUF', '2026-07-01', null),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        insertIncome(pool, userA, incomeA, '0', 'HUF', '2026-07-01', null),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        insertIncome(pool, userA, incomeA, '10', 'EUR', '2026-07-01', null),
      ).rejects.toMatchObject({ code: '23503' });
      await expect(
        insertIncome(pool, userA, incomeA, '10', 'HUF', '2026-07-02', '2026-07-01'),
      ).rejects.toMatchObject({ code: '23514' });

      const basicIncome = await insertIncome(
        pool,
        userA,
        incomeA,
        '10.123456789012',
        'HUF',
        '2026-07-01',
        null,
      );
      expect(
        (
          await pool.query<{ amount: string }>(
            'SELECT amount::text FROM mymoneymap.basic_incomes WHERE id = $1',
            [basicIncome],
          )
        ).rows[0]?.amount,
      ).toBe('10.123456789012');
      await expect(
        pool.query(`UPDATE mymoneymap.categories SET kind = 'spending' WHERE id = $1`, [incomeA]),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('protects category references and enforces journal owner and economic-kind linkage', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userA = await insertUser(pool);
      const userB = await insertUser(pool);
      const incomeA = await insertCategory(pool, userA, 'income');
      const spendingA = await insertCategory(pool, userA, 'spending');
      const spendingB = await insertCategory(pool, userB, 'spending');
      await insertIncome(pool, userA, incomeA, '1000', 'HUF', '2026-07-01', null);

      await expect(
        insertJournalHeader(pool, userA, spendingB, 'external_expense'),
      ).rejects.toMatchObject({ code: '23503' });
      await expect(
        insertJournalHeader(pool, userA, incomeA, 'external_expense'),
      ).rejects.toMatchObject({ code: '23514' });

      await expect(
        pool.query('DELETE FROM mymoneymap.categories WHERE id = $1', [incomeA]),
      ).rejects.toMatchObject({ code: '23503' });
      await pool.query('DELETE FROM mymoneymap.categories WHERE id = $1', [spendingA]);
    });
  });
});

async function relation(pool: Pool, name: string): Promise<string | null> {
  return (
    (
      await pool.query<{ relation: string | null }>(`SELECT to_regclass($1)::text AS relation`, [
        `mymoneymap.${name}`,
      ])
    ).rows[0]?.relation ?? null
  );
}

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic Planning User','1990-01-01','premium',
             now(),now(),now())`,
    [id, `${id}@example.test`],
  );
  return id;
}

async function insertRule(pool: Pool, userId: string, percent: string): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.budget_rules
      (id,user_id,label,percent,created_at,updated_at)
     VALUES ($1,$2,$3,$4,now(),now())`,
    [id, userId, `Rule ${id}`, percent],
  );
  return id;
}

async function insertCategory(
  pool: Pool,
  userId: string,
  kind: 'income' | 'spending',
): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.categories
      (id,user_id,label,kind,color,created_at,updated_at)
     VALUES ($1,$2,$3,$4,'#AABBCC',now(),now())`,
    [id, userId, `${kind} ${id}`, kind],
  );
  return id;
}

async function insertIncome(
  pool: Pool,
  userId: string,
  categoryId: string,
  amount: string,
  currency: string,
  validFrom: string,
  validTo: string | null,
): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.basic_incomes
      (id,user_id,label,amount,currency,valid_from,valid_to,category_id,created_at,updated_at)
     VALUES ($1,$2,'Salary',$3,$4,$5,$6,$7,now(),now())`,
    [id, userId, amount, currency, validFrom, validTo, categoryId],
  );
  return id;
}

async function insertJournalHeader(
  pool: Pool,
  userId: string,
  categoryId: string,
  economicType: 'external_expense',
): Promise<void> {
  await pool.query(
    `INSERT INTO mymoneymap.journal_entries
      (id,user_id,economic_type,category_id,source_module,idempotency_key_hash,posted_on,
       effective_at,created_at,actor_user_id)
     VALUES ($1,$2,$3,$4,'manual',$5,'2026-07-10',now(),now(),$2)`,
    [
      randomUUID(),
      userId,
      economicType,
      categoryId,
      randomUUID().replaceAll('-', '').padEnd(64, '0'),
    ],
  );
}
