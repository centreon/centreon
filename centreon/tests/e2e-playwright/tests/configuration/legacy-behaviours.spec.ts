import { randomUUID } from 'node:crypto';

import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import type { ClapiAction } from '../../helpers/CentreonApi';
import { queryCentreonDb } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Hosts/04, 06 and 08: listing filters and searches of the legacy
 * pages, and locked templates.
 */
const action = (object: string, verb: string, values: string): ClapiAction => ({
  action: verb,
  object,
  values
});

let name: string;
let teardown: Array<ClapiAction>;

test.beforeEach(async ({ page }) => {
  name = `pw-legacy-${randomUUID().slice(0, 6)}`;
  teardown = [];
  await signIn(page, adminUser);
});

test.afterEach(async ({ adminApi }) => {
  await adminApi.cleanUp(teardown);
});

test('hides locked host templates unless asked, and freezes their form', async ({
  adminApi,
  page
}) => {
  teardown.push(action('HTPL', 'DEL', name));
  await adminApi.provision([action('HTPL', 'ADD', `${name};${name};;;;`)]);
  queryCentreonDb(
    `UPDATE host SET host_locked = '1' WHERE host_name = '${name}'`
  );
  const templates = new LegacyListingPage(page, '60103');

  await templates.open(name);
  await expect(templates.row(name)).toHaveCount(0);

  await test.step('The locked elements filter shows it', async () => {
    await templates.searchWithFilter('displayLocked');
    await expect(templates.row(name)).toHaveCount(1);
  });

  await test.step('Its form is read-only', async () => {
    await templates.openForm(name, { editable: false });
    await expect(templates.field('host_name')).toHaveAttribute(
      'type',
      'hidden'
    );
    await expect(templates.field('command_command_id')).toBeDisabled();
    await expect(templates.field('host_location')).toBeDisabled();
  });
});

test('lists the services of disabled hosts on demand only', async ({
  adminApi,
  page
}) => {
  teardown.push(action('HOST', 'DEL', name));
  await adminApi.provision([
    action('HOST', 'ADD', `${name};${name};192.0.2.1;generic-host;Central;`),
    action('SERVICE', 'ADD', `${name};${name}-svc;generic-service`),
    action('HOST', 'SETPARAM', `${name};activate;0`)
  ]);
  const services = new LegacyListingPage(page, '60201');

  await services.open(name);
  await expect(services.row(`${name}-svc`)).toHaveCount(0);

  await services.searchWithFilter('statusHostFilter');
  await expect(services.row(`${name}-svc`)).toHaveCount(1);
});

for (const { listing, page: topologyPage } of [
  { listing: 'host templates', page: '60103' },
  { listing: 'SNMP traps', page: '61701' }
]) {
  test(`keeps the last search of the ${listing} listing`, async ({ page }) => {
    const objects = new LegacyListingPage(page, topologyPage);
    await objects.open(name);
    await new LegacyListingPage(page, '60104').open();
    await objects.open();
    await expect(page.locator('input[name^="search"]').first()).toHaveValue(
      name
    );
  });
}
