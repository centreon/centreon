import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { hostActions } from '../../fixtures/monitoring';
import { expect, test } from '../../fixtures/test';
import { ServicesConfigurationPage } from '../../pages/ServicesConfigurationPage';

/**
 * Replaces Services-configuration/03: create a service on a host, change its
 * properties, duplicate it and delete both, on a host of its own.
 */
test.use({ storageState: adminStorageStatePath });

test('creates, edits, duplicates and deletes a service', async ({
  adminApi,
  page
}) => {
  const suffix = randomUUID().slice(0, 8);
  const host = `pw-svc-host-${suffix}`;
  const name = `pw-service-${suffix}`;
  const duplicate = `${name}_1`;
  const services = new ServicesConfigurationPage(page);

  await adminApi.provision(
    hostActions({ hostGroup: 'Linux-Servers', name: host })
  );

  try {
    await services.createService({ host, name, template: 'Ping-LAN' });
    await services.open(host);
    await expect(services.row(name)).toBeVisible();

    await services.edit(name, { service_max_check_attempts: '7' });
    await services.openForm(name);
    await expect(services.field('service_max_check_attempts')).toHaveValue('7');

    await services.open(host);
    await services.bulk('Duplicate', [name]);
    await expect(services.row(duplicate)).toBeVisible();
    await test.step('The duplicate keeps the properties', async () => {
      await services.openForm(duplicate);
      await expect(services.field('service_max_check_attempts')).toHaveValue(
        '7'
      );
    });

    await services.open(host);
    await services.bulk('Delete', [name, duplicate]);
    await expect(services.row(name)).toHaveCount(0);
    await expect(services.row(duplicate)).toHaveCount(0);
  } finally {
    await adminApi
      .provision([{ action: 'DEL', object: 'HOST', values: host }], {
        tolerate: [404, 409]
      })
      .catch(() => undefined);
  }
});
