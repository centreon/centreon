import { randomUUID } from 'node:crypto';

import { restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { resourcesHostName } from '../../fixtures/resources';
import { expect, test } from '../../fixtures/test';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { signIn } from '../../helpers/session';
import { EventLogsPage } from '../../pages/EventLogsPage';

/**
 * Replaces Logs/07: a user with the Event Logs menu only sees the events of
 * the resources its ACL grants. The administrator view gives the hosts with
 * events, so the hosts the user must not see are real ones.
 */
test('shows a restricted user the events of its granted hosts only', async ({
  adminApi,
  baseURL,
  browser,
  page
}) => {
  test.setTimeout(300_000);
  await ensureResourcesMonitored(baseURL ?? '');
  const name = `pw-events-${randomUUID().slice(0, 6)}`;
  const menus = [{ access: 'ro' as const, topology: 'Monitoring;Event Logs' }];
  const withoutResources = restrictedUser({ menus, name: `${name}-none` });
  const withHost = restrictedUser({
    hosts: [resourcesHostName],
    menus,
    name
  });

  await adminApi.provision([
    ...withoutResources.provisioning,
    ...withHost.provisioning
  ]);

  try {
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await signIn(adminPage, adminUser);
    const adminLogs = new EventLogsPage(adminPage);
    await adminLogs.open();
    const allHosts = await adminLogs.filterHosts();
    await adminContext.close();
    expect(allHosts).toContain(resourcesHostName);
    expect(allHosts.length).toBeGreaterThan(1);

    const logs = new EventLogsPage(page);
    await test.step('No granted resource, no event', async () => {
      await signIn(page, withoutResources.credentials);
      await logs.open();
      expect(await logs.filterHosts()).toEqual([]);
    });

    await test.step('The granted host only', async () => {
      await page.context().clearCookies();
      await signIn(page, withHost.credentials);
      await logs.open();
      expect(await logs.filterHosts()).toEqual([resourcesHostName]);
    });
  } finally {
    await adminApi.cleanUp([
      ...withoutResources.teardown,
      ...withHost.teardown
    ]);
  }
});
