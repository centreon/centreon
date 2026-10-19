import { randomUUID } from 'node:crypto';

import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import type { CentreonApi, ClapiAction } from '../../helpers/CentreonApi';
import { queryCentreonDb } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Hosts/05 and Services-configuration/08: a mass change writes the
 * same values on the selected objects only, and a host template change
 * replaces or adds the services the templates deploy.
 */
const action = (object: string, verb: string, values: string): ClapiAction => ({
  action: verb,
  object,
  values
});

const rows = (sql: string): Array<Array<string>> =>
  queryCentreonDb(sql)
    .trim()
    .split('\n')
    .map((row) => row.split('\t'));

const inList = (names: Array<string>): string =>
  names.map((name) => `'${name}'`).join(', ');

let prefix: string;
let teardown: Array<ClapiAction>;

test.beforeEach(async ({ page }) => {
  prefix = `pw-mc-${randomUUID().slice(0, 6)}`;
  teardown = [];
  await signIn(page, adminUser);
});

test.afterEach(async ({ adminApi }) => {
  await adminApi.cleanUp(teardown);
});

const addHosts = async (
  adminApi: CentreonApi,
  count: number
): Promise<Array<string>> => {
  const hosts = Array.from(
    { length: count },
    (_, i) => `${prefix}-host-${i + 1}`
  );
  teardown.push(...hosts.map((host) => action('HOST', 'DEL', host)));
  await adminApi.provision(
    hosts.map((host) =>
      action('HOST', 'ADD', `${host};${host};192.0.2.1;generic-host;Central;`)
    )
  );

  return hosts;
};

test('writes the same values on the selected hosts only', async ({
  adminApi,
  page
}) => {
  const [first, second, third, untouched] = await addHosts(adminApi, 4);
  const hosts = new LegacyListingPage(page, '60101');
  await hosts.open(prefix);
  await hosts.openMassChange([first, second, third]);
  await hosts.choose(hosts.select2('host_location'), 'Africa/Algiers');
  await hosts.choose(hosts.select2('command_command_id'), 'check_http');
  await hosts.field('host_retry_check_interval').fill('3');
  await hosts.saveMassChange();

  expect(
    rows(
      `SELECT h.host_name, IFNULL(tz.timezone_name, '-'), IFNULL(c.command_name, '-'), IFNULL(h.host_retry_check_interval, '-') FROM host h LEFT JOIN timezone tz ON tz.timezone_id = h.host_location LEFT JOIN command c ON c.command_id = h.command_command_id WHERE h.host_name LIKE '${prefix}%' ORDER BY h.host_name`
    )
  ).toEqual([
    [first, 'Africa/Algiers', 'check_http', '3'],
    [second, 'Africa/Algiers', 'check_http', '3'],
    [third, 'Africa/Algiers', 'check_http', '3'],
    [untouched, '-', '-', '-']
  ]);
});

test('writes the same values on the selected services only', async ({
  adminApi,
  page
}) => {
  const [host] = await addHosts(adminApi, 1);
  const services = ['svc-1', 'svc-2', 'svc-3', 'svc-untouched'];
  await adminApi.provision(
    services.map((service) =>
      action('SERVICE', 'ADD', `${host};${service};generic-service`)
    )
  );
  const listing = new LegacyListingPage(page, '60201');
  await listing.open(host);
  await listing.openMassChange(services.slice(0, 3));
  await listing.choose(listing.select2('command_command_id'), 'check_http');
  await listing.field('service_max_check_attempts').fill('2');
  await listing.field('service_retry_check_interval').fill('3');
  await listing.saveMassChange();

  expect(
    rows(
      `SELECT s.service_description, IFNULL(c.command_name, '-'), IFNULL(s.service_max_check_attempts, '-'), IFNULL(s.service_retry_check_interval, '-') FROM service s JOIN host_service_relation r ON r.service_service_id = s.service_id JOIN host h ON h.host_id = r.host_host_id LEFT JOIN command c ON c.command_id = s.command_command_id WHERE h.host_name = '${host}' ORDER BY s.service_description`
    )
  ).toEqual([
    ['svc-1', 'check_http', '2', '3'],
    ['svc-2', 'check_http', '2', '3'],
    ['svc-3', 'check_http', '2', '3'],
    ['svc-untouched', '-', '-', '-']
  ]);
});

for (const { keepsPrevious, mode } of [
  { keepsPrevious: false, mode: 'Replacement' },
  { keepsPrevious: true, mode: 'Incremental' }
]) {
  test(`deploys the services of a new host template in ${mode} mode`, async ({
    adminApi,
    page
  }) => {
    const hosts = await addHosts(adminApi, 2);
    const previous = {
      service: `${prefix}-prev-svc`,
      template: `${prefix}-prev-tpl`
    };
    const next = {
      service: `${prefix}-next-svc`,
      template: `${prefix}-next-tpl`
    };
    for (const { service, template } of [previous, next]) {
      teardown.push(
        action('STPL', 'DEL', service),
        action('HTPL', 'DEL', template)
      );
      await adminApi.provision([
        action('HTPL', 'ADD', `${template};${template};;;;`),
        action('STPL', 'ADD', `${service};${service};`),
        action('STPL', 'ADDHOSTTEMPLATE', `${service};${template}`)
      ]);
    }
    await adminApi.provision(
      hosts.flatMap((host) => [
        action('HOST', 'SETTEMPLATE', `${host};${previous.template}`),
        action('HOST', 'APPLYTPL', host)
      ])
    );
    const deployed = (): Array<Array<string>> =>
      rows(
        `SELECT h.host_name, t.service_description FROM host h JOIN host_service_relation r ON r.host_host_id = h.host_id JOIN service s ON s.service_id = r.service_service_id JOIN service t ON t.service_id = s.service_template_model_stm_id WHERE h.host_name IN (${inList(hosts)}) ORDER BY h.host_name, t.service_description`
      );
    expect(deployed()).toEqual(hosts.map((host) => [host, previous.service]));

    const listing = new LegacyListingPage(page, '60101');
    await listing.open(prefix);
    await listing.openMassChange(hosts);
    await page.locator('#template_add').click();
    await listing.choose(page.locator('span[id^="select2-tpSelect_"]').last(), next.template);
    await listing.pickRadio(
      'mc_mod_tplp[mc_mod_tplp]',
      keepsPrevious ? '0' : '1'
    );
    await listing.pickRadio('dupSvTplAssoc[dupSvTplAssoc]', '1');
    await listing.saveMassChange();

    expect(deployed()).toEqual(
      hosts.flatMap((host) => [
        [host, next.service],
        ...(keepsPrevious ? [[host, previous.service]] : [])
      ])
    );
  });
}
