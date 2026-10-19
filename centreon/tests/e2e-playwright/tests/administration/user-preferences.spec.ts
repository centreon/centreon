import { randomUUID } from 'node:crypto';

import { restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { CentreonUiParametersPage } from '../../pages/CentreonUiParametersPage';
import { LoginPage } from '../../pages/LoginPage';
import { MyAccountPage } from '../../pages/MyAccountPage';

/**
 * Replaces Administration/01 and 02: the default page a user chooses opens
 * after signing in, and the top counters refresh at the configured interval.
 */
for (const { admin, menuPath, url } of [
  { admin: true, menuPath: 'Home > Dashboards', url: /home\/dashboards/ },
  {
    admin: false,
    menuPath: 'Configuration > Hosts',
    url: /p=60101|configuration\/hosts/
  }
]) {
  test(`opens the default page chosen by ${admin ? 'an admin' : 'a non-admin'} user`, async ({
    adminApi,
    page
  }) => {
    const user = restrictedUser({
      contactParams: admin ? { admin: '1' } : {},
      menus: [
        { access: 'rw', topology: 'Administration;Parameters;My Account' },
        { access: 'rw', topology: 'Configuration;Hosts' },
        { access: 'ro', topology: 'Monitoring;Resources Status' }
      ],
      name: `pw-default-page-${randomUUID().slice(0, 6)}`
    });
    await adminApi.provision(user.provisioning);

    try {
      await signIn(page, user.credentials);
      await new MyAccountPage(page).setDefaultPage(menuPath);
      await page.context().clearCookies();

      const login = new LoginPage(page);
      await login.open();
      await login.login(user.credentials);
      await expect(page).toHaveURL(url, { timeout: 30_000 });
    } finally {
      await adminApi.cleanUp(user.teardown);
    }
  });
}

test('refreshes the top counters at the configured interval', async ({
  adminApi,
  browser,
  page
}) => {
  test.setTimeout(120_000);
  const interval = 10;
  await signIn(page, adminUser);
  const parameters = new CentreonUiParametersPage(page);
  const original = await parameters.refreshIntervalForStatistics();
  await parameters.setRefreshIntervalForStatistics(String(interval));

  try {
    const { statistics_default_refresh_interval: saved } = await adminApi.v2<{
      statistics_default_refresh_interval: number;
    }>('GET', '/administration/parameters');
    expect(saved).toBe(interval);

    // The interval applies to the sessions opened afterwards.
    const context = await browser.newContext();
    try {
      const visitor = await context.newPage();
      const calls: Array<number> = [];
      visitor.on('request', (request) => {
        if (/centreon_topcounter.*action=servicesStatus/.test(request.url())) {
          calls.push(Date.now());
        }
      });
      await signIn(visitor, adminUser);
      await visitor.goto('/centreon/monitoring/resources');
      await expect
        .poll(() => calls.length, { timeout: 60_000 })
        .toBeGreaterThanOrEqual(3);
      const gaps = calls
        .slice(1)
        .map((time, index) => (time - calls[index]) / 1000);
      for (const gap of gaps.slice(1)) {
        expect(gap).toBeGreaterThan(interval - 1);
        expect(gap).toBeLessThan(interval + 3);
      }
    } finally {
      await context.close();
    }
  } finally {
    await parameters.setRefreshIntervalForStatistics(original);
  }
});
