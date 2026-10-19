import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { queryCentreonDb } from '../../helpers/docker';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Snmp-Traps/02: a trap group is renamed from its form, its
 * duplicate keeps its traps, and both are deleted from the listing. CLAPI
 * does not handle trap groups: the group is seeded in the database.
 */
test.use({ storageState: adminStorageStatePath });

const vendor = 'pw-trap-vendor';

const trapsOf = (group: string): Array<string> =>
  queryCentreonDb(
    `SELECT t.traps_name FROM traps_group g JOIN traps_group_relation r ON r.traps_group_id = g.traps_group_id JOIN traps t ON t.traps_id = r.traps_id WHERE g.traps_group_name = '${group}' ORDER BY t.traps_name`
  )
    .trim()
    .split('\n')
    .filter(Boolean);

test('renames, duplicates and deletes a trap group', async ({
  adminApi,
  page
}) => {
  const name = `pw-trapgroup-${randomUUID().slice(0, 6)}`;
  const renamed = `${name}-renamed`;
  const traps = [`${name}-trap-1`, `${name}-trap-2`];
  await adminApi.provision(
    [{ action: 'ADD', object: 'VENDOR', values: `${vendor};${vendor}` }],
    { tolerate: [409] }
  );
  await adminApi.provision(
    traps.flatMap((trap, index) => [
      {
        action: 'ADD',
        object: 'TRAP',
        values: `${trap};.1.3.6.1.4.1.50001.${index}`
      },
      { action: 'SETPARAM', object: 'TRAP', values: `${trap};vendor;${vendor}` }
    ])
  );
  queryCentreonDb(
    `INSERT INTO traps_group (traps_group_name) VALUES ('${name}')`
  );
  queryCentreonDb(
    `INSERT INTO traps_group_relation (traps_group_id, traps_id) SELECT g.traps_group_id, t.traps_id FROM traps_group g, traps t WHERE g.traps_group_name = '${name}' AND t.traps_name IN ('${traps.join("', '")}')`
  );
  const groups = new LegacyListingPage(page, '61705');

  try {
    await groups.open(name);
    await groups.edit(name, { name: renamed });
    await groups.open(renamed);
    await expect(groups.row(renamed)).toBeVisible();
    expect(trapsOf(renamed)).toEqual(traps);

    await groups.bulk('Duplicate', [renamed]);
    await expect(groups.row(`${renamed}_1`)).toBeVisible();
    expect(trapsOf(`${renamed}_1`)).toEqual(traps);

    await groups.open(renamed);
    await groups.bulk('Delete', [renamed, `${renamed}_1`]);
    await expect(groups.row(renamed)).toHaveCount(0);
    await expect(groups.row(`${renamed}_1`)).toHaveCount(0);
  } finally {
    queryCentreonDb(
      `DELETE FROM traps_group WHERE traps_group_name LIKE '${name}%'`
    );
    await adminApi.cleanUp(
      traps.map((trap) => ({ action: 'DEL', object: 'TRAP', values: trap }))
    );
  }
});
