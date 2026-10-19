import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import type { ClapiAction } from '../../helpers/CentreonApi';
import { execInWebContainer, queryCentreonDb } from '../../helpers/docker';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Services-configuration/12: a service by host group is exported on
 * each host of its group, changes from its form, and is duplicated and deleted from
 * its listing.
 */
test.use({ storageState: adminStorageStatePath });

const action = (object: string, verb: string, values: string): ClapiAction => ({
  action: verb,
  object,
  values
});

test('exports, edits, duplicates and deletes a service by host group', async ({
  adminApi,
  page
}) => {
  const prefix = `pw-hgsvc-${randomUUID().slice(0, 6)}`;
  const group = `${prefix}-hg`;
  const name = `${prefix}-svc`;
  const hosts = [`${prefix}-host-1`, `${prefix}-host-2`];
  await adminApi.provision([
    action('HG', 'ADD', `${group};${group}`),
    ...hosts.flatMap((host) => [
      action(
        'HOST',
        'ADD',
        `${host};${host};192.0.2.1;generic-host;Central;${group}`
      )
    ]),
    action('HGSERVICE', 'ADD', `${group};${name};generic-service`),
    action(
      'HGSERVICE',
      'SETPARAM',
      `${group};${name};check_command;check_centreon_ping`
    )
  ]);
  const services = new LegacyListingPage(page, '60202');
  // Its search form also sends a template filter: search through the URL.
  const open = () =>
    page.goto(
      `/centreon/main.get.php?p=60202&Search=1&search=${prefix}&template=0&status=0`
    );

  try {
    await test.step('The export deploys it on each host of the group', async () => {
      await adminApi.v2('GET', '/configuration/monitoring-servers/1/generate');
      const exportedOn = execInWebContainer(
        'cat /var/cache/centreon/config/engine/1/services.cfg'
      )
        .split('define service')
        .filter((block) => new RegExp(`service_description\\s+${name}\\s`).test(block))
        .map((block) => /host_name\s+(\S+)/.exec(block)?.[1])
        .sort();
      expect(exportedOn).toEqual(hosts);
    });

    await test.step('Its form changes it', async () => {
      await open();
      await services.openForm(name);
      await services.field('service_max_check_attempts').fill('7');
      await page.locator('#validForm input[name^="submit"]').first().click();
      await expect
        .poll(() =>
          queryCentreonDb(
            `SELECT service_max_check_attempts FROM service WHERE service_description = '${name}'`
          ).trim()
        )
        .toBe('7');
    });

    await test.step('It is duplicated and deleted from the listing', async () => {
      await open();
      await services.bulk('Duplicate', [name]);
      await open();
      await expect(services.row(`${name}_1`)).toBeVisible();
      await services.bulk('Delete', [name, `${name}_1`]);
      await open();
      await expect(services.row(name)).toHaveCount(0);
      await expect(services.row(`${name}_1`)).toHaveCount(0);
    });
  } finally {
    await adminApi.cleanUp([
      action('HGSERVICE', 'DEL', `${group};${name}`),
      action('HGSERVICE', 'DEL', `${group};${name}_1`),
      ...hosts.map((host) => action('HOST', 'DEL', host)),
      action('HG', 'DEL', group)
    ]);
  }
});
