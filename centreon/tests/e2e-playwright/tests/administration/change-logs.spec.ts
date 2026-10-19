import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { ChangeLogsPage } from '../../pages/ChangeLogsPage';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Logs/01-05: every change made through the API v2 is written to the
 * configuration change log. The Cypress suite spent one scenario (and one
 * fresh stack) per action and read the first log line; each test here runs
 * the whole lifecycle of one object type and reads the lines of that object.
 */
interface AuditedObject {
  /** Body creating an object called `name` (`active` sets `is_activated`). */
  body: (name: string, alias: string, active: boolean) => object;
  /** Fields of the name and alias in the log details. */
  fields: { alias: string; name: string };
  label: string;
  path: string;
  /**
   * How the object is disabled then enabled: through the API, through its
   * legacy listing (the API does not log it for service categories, so
   * Cypress used the listing too), or not at all.
   */
  toggles: 'api' | { listing: string } | false;
}

const objects: Array<AuditedObject> = [
  {
    body: (name, alias, active) => ({
      alias,
      comment: 'audit',
      is_activated: active,
      name
    }),
    fields: { alias: 'hc_alias', name: 'hc_name' },
    label: 'host category',
    path: '/configuration/hosts/categories',
    toggles: 'api'
  },
  {
    body: (name, alias, active) => ({ alias, is_activated: active, name }),
    fields: { alias: 'sc_alias', name: 'sc_name' },
    label: 'service category',
    path: '/configuration/services/categories',
    toggles: { listing: '60209' }
  },
  {
    body: (name, alias, active) => ({
      alias,
      icon_id: 1,
      is_activated: active,
      level: 2,
      name
    }),
    fields: { alias: 'hc_alias', name: 'hc_name' },
    label: 'host severity',
    path: '/configuration/hosts/severities',
    toggles: 'api'
  },
  {
    body: (name, alias, active) => ({
      alias,
      icon_id: 1,
      is_activated: active,
      level: 2,
      name
    }),
    fields: { alias: 'sc_description', name: 'sc_name' },
    label: 'service severity',
    path: '/configuration/services/severities',
    toggles: 'api'
  },
  {
    body: (name, alias) => ({
      alias,
      days: [{ day: 1, time_range: '00:00-12:00' }],
      exceptions: [],
      name,
      templates: []
    }),
    fields: { alias: 'alias', name: 'name' },
    label: 'time period',
    path: '/configuration/timeperiods',
    toggles: false
  }
];

test.use({ storageState: adminStorageStatePath });

for (const object of objects) {
  test(`logs the API changes of a ${object.label}`, async ({
    adminApi,
    page
  }) => {
    const name = `pw-audit-${randomUUID().slice(0, 8)}`;
    const logs = new ChangeLogsPage(page);
    const { id } = await adminApi.v2<{ id: number }>(
      'POST',
      object.path,
      object.body(name, 'first alias', true)
    );
    const update = (alias: string, active = true) =>
      adminApi.v2(
        'PUT',
        `${object.path}/${id}`,
        object.body(name, alias, active)
      );

    try {
      await update('second alias');
      if (object.toggles === 'api') {
        await update('second alias', false);
        await update('second alias', true);
      } else if (object.toggles) {
        const listing = new LegacyListingPage(page, object.toggles.listing);
        await listing.open(name);
        await listing.toggleStatus(name);
        await listing.toggleStatus(name);
      }
    } finally {
      await adminApi.v2('DELETE', `${object.path}/${id}`);
    }

    await logs.open(name);
    const actions = object.toggles
      ? (['Added', 'Changed', 'Disabled', 'Enabled', 'Deleted'] as const)
      : (['Added', 'Changed', 'Deleted'] as const);
    for (const action of actions) {
      await expect(logs.entries(action).first()).toBeVisible();
    }

    await test.step('The details carry the created and updated values', async () => {
      expect(await logs.fieldChanges(object.fields.name)).toContainEqual({
        after: name,
        before: ''
      });
      await logs.open(name);
      expect(await logs.fieldChanges(object.fields.alias)).toContainEqual({
        after: 'second alias',
        before: 'first alias'
      });
    });
  });
}
