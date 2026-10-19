import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  legacyObjects,
  removeLegacyObject,
  seedLegacyObject
} from '../../fixtures/legacy-objects';
import { expect, test } from '../../fixtures/test';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces the edit, duplicate and delete scenarios the Cypress suite repeats
 * for each legacy configuration object (Commands/02, Contacts/02,
 * HostGroups/02, Hosts/02-03-07, Notifications/01,
 * Services-configuration/01-04-05-07-10-11, Snmp-Traps/01-03,
 * Time-Period/01): one lifecycle per object type on a freshly seeded object.
 */
test.use({ storageState: adminStorageStatePath });

for (const object of legacyObjects) {
  test(`${object.duplicable === false ? 'edits' : 'edits, duplicates'} and deletes a ${object.label}`, async ({
    adminApi,
    page
  }) => {
    const name = `pw-${object.clapi.toLowerCase()}-${randomUUID().slice(0, 8)}`;
    const duplicate = `${name}_1`;
    const edited = `${name} edited`;
    const listing = new LegacyListingPage(page, object.topologyPage);

    await seedLegacyObject(adminApi, object, name);

    try {
      await listing.open(name);

      await listing.edit(name, { [object.editField]: edited });
      await listing.openForm(name);
      await expect(listing.field(object.editField)).toHaveValue(edited);

      if (object.duplicable !== false) {
        await listing.open(name);
        await listing.bulk('Duplicate', [name]);
        await expect(listing.row(duplicate)).toBeVisible();
        await test.step('The duplicate keeps the properties', async () => {
          await listing.openForm(duplicate);
          await expect(listing.field(object.editField)).toHaveValue(edited);
        });
      }

      await listing.open(name);
      await listing.bulk(
        'Delete',
        object.duplicable === false ? [name] : [name, duplicate]
      );
      await expect(listing.row(name)).toHaveCount(0);
      await expect(listing.row(duplicate)).toHaveCount(0);
    } finally {
      await removeLegacyObject(adminApi, object, name);
    }
  });
}

test('leaves a configuration the engine accepts', async ({ adminApi }) => {
  // The shared objects the tests keep must not break the export.
  await adminApi.v2('GET', '/configuration/monitoring-servers/1/generate');
});
