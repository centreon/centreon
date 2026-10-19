import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { queryCentreonDb } from '../../helpers/docker';
import { waitForServiceMetrics } from '../../helpers/metrics';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces the last scenario of Services-configuration/07: a meta service
 * created from the form is listed with the meta services, not with the
 * services, by the endpoint the service pickers use; the configuration it
 * adds still exports.
 */
test.use({ storageState: adminStorageStatePath });

// The metric picker lists the metrics the services already reported.
test.beforeAll(async () => {
  test.setTimeout(360_000);
  await waitForServiceMetrics({ metrics: ['rta'], service: 'Ping' });
});

interface PickerItem {
  id: string;
  text: string;
}

test('lists a meta service created from the form among the meta services only', async ({
  adminApi,
  page
}) => {
  const name = `pw-meta-${randomUUID().slice(0, 6)}`;
  const label = `Meta - ${name}`;
  const metas = new LegacyListingPage(page, '60204');

  try {
    await page.goto('/centreon/main.get.php?p=60204&o=a');
    await metas.field('meta_name').fill(name);
    await metas.field('max_check_attempts').fill('3');
    await metas.field('calcul_type').selectOption({ label: 'Average' });
    await metas.pickRadio('meta_select_mode[meta_select_mode]', '2');
    await metas.field('regexp_str').fill('Ping');
    await metas.choose(metas.select2('metric'), 'rta');
    await page.locator('#validForm input[name^="submit"]').first().click();
    await metas.open(name);
    await expect(metas.row(name)).toBeVisible();

    const picker = async (selection: string): Promise<Array<string>> => {
      const response = await page.request.get(
        `/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list&page_limit=200&page=1&s=${selection}`
      );
      const { items } = (await response.json()) as { items: Array<PickerItem> };

      return items.map(({ text }) => text);
    };
    expect(await picker('all')).toContain(label);
    expect(await picker('s')).not.toContain(label);
    const onlyMetas = await picker('m');
    expect(onlyMetas).toContain(label);
    expect(onlyMetas.every((text) => text.startsWith('Meta - '))).toBe(true);

    await adminApi.v2('GET', '/configuration/monitoring-servers/1/generate');

    // The listing also removes the service the form created for it.
    await metas.open(name);
    await metas.bulk('Delete', [name]);
    await expect(metas.row(name)).toHaveCount(0);
  } finally {
    queryCentreonDb(`DELETE FROM meta_service WHERE meta_name = '${name}'`);
  }
});
