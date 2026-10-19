import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { execInWebContainer } from '../../helpers/docker';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';

/**
 * Replaces Web-server-configuration/01: once the web server serves Centreon
 * under another base URI, users sign in and the monitoring and configuration
 * pages work under it. The web server configuration is restored afterwards.
 */
const conf = '/etc/httpd/conf.d/10-centreon.conf';
const uri = '/monitor';

const reload = async (
  page: {
    request: { get: (url: string) => Promise<{ status: () => number }> };
  },
  path: string
) => {
  execInWebContainer('httpd -t && httpd -k graceful');
  await expect
    .poll(async () => (await page.request.get(path)).status(), {
      timeout: 30_000
    })
    .toBeLessThan(400);
};

test('serves the platform under a custom base URI', async ({
  browser,
  page
}) => {
  test.setTimeout(180_000);
  execInWebContainer(`cp ${conf} ${conf}.pw-backup`);

  try {
    execInWebContainer(`sed -i "0,/centreon/s//${uri.slice(1)}/" ${conf}`);
    await reload(page, `${uri}/`);

    const context = await browser.newContext({
      storageState: { cookies: [], origins: [] }
    });
    try {
      const visitor = await context.newPage();
      await test.step('A user signs in under the new URI', async () => {
        await visitor.goto(`${uri}/`);
        const login = new LoginPage(visitor);
        await login.expectVisible();
        await login.login(adminUser);
        await expect(visitor).toHaveURL(
          new RegExp(`${uri}/monitoring/resources`),
          {
            timeout: 30_000
          }
        );
      });

      await test.step('The monitoring pages and API answer under it', async () => {
        await new MainHeader(visitor).expectLoaded();
        const response = await visitor.request.get(
          `${uri}/api/latest/monitoring/resources?search=${encodeURIComponent(JSON.stringify({ name: { $eq: 'Centreon-Server' } }))}`
        );
        expect(response.ok()).toBe(true);
        const { result } = (await response.json()) as {
          result: Array<{ name: string }>;
        };
        expect(result.map(({ name }) => name)).toContain('Centreon-Server');
      });

      await test.step('The legacy configuration pages load their assets', async () => {
        const failed: Array<string> = [];
        visitor.on('response', (response) => {
          if (response.status() >= 400 && response.url().includes(uri)) {
            failed.push(`${response.status()} ${response.url()}`);
          }
        });
        await visitor.goto(`${uri}/main.get.php?p=60101`);
        await expect(
          visitor.getByRole('link', { exact: true, name: 'Centreon-Server' })
        ).toBeVisible({ timeout: 30_000 });
        expect(failed).toEqual([]);
      });
    } finally {
      await context.close();
    }
  } finally {
    execInWebContainer(`mv -f ${conf}.pw-backup ${conf}`);
    await reload(page, '/centreon/');
  }
});
