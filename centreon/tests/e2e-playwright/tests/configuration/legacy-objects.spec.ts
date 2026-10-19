import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  legacyObjectCleanup,
  legacyObjectProvisioning,
  legacyObjects
} from '../../fixtures/legacy-objects';
import { expect, test } from '../../fixtures/test';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces the edit, duplicate and delete scenarios the Cypress suite repeats
 * for each legacy configuration object (Contacts/02, Hosts/02-03-07,
 * Services-configuration/01-04-05, Snmp-Traps/01-03, Time-Period/01): one
 * lifecycle per object type on a freshly seeded object.
 */
test.use({ storageState: adminStorageStatePath });

for (const object of legacyObjects) {
  test(`edits, duplicates and deletes a ${object.label}`, async ({
    adminApi,
    page
  }) => {
    const name = `pw-${object.clapi.toLowerCase()}-${randomUUID().slice(0, 8)}`;
    const duplicate = `${name}_1`;
    const edited = `${name} edited`;
    const listing = new LegacyListingPage(page, object.topologyPage);

    // Shared objects the type relies on, kept between tests.
    await adminApi.provision(object.prerequisites ?? [], { tolerate: [409] });
    await adminApi.provision(legacyObjectProvisioning(object, name));

    try {
      await listing.open(name);

      await listing.edit(name, { [object.editField]: edited });
      await listing.openForm(name);
      await expect(listing.field(object.editField)).toHaveValue(edited);

      await listing.open(name);
      await listing.bulk('Duplicate', [name]);
      await expect(listing.row(duplicate)).toBeVisible();
      await test.step('The duplicate keeps the properties', async () => {
        await listing.openForm(duplicate);
        await expect(listing.field(object.editField)).toHaveValue(edited);
      });

      await listing.open(name);
      await listing.bulk('Delete', [name, duplicate]);
      await expect(listing.row(name)).toHaveCount(0);
      await expect(listing.row(duplicate)).toHaveCount(0);
    } finally {
      await adminApi
        .provision(legacyObjectCleanup(object, name), { tolerate: [404, 409] })
        .catch(() => undefined);
    }
  });
}
