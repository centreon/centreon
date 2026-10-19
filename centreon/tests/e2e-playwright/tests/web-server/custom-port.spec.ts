import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { execInWebContainer, webContainerAddress } from '../../helpers/docker';
import { HostsPage } from '../../pages/HostsPage';
import { LoginPage } from '../../pages/LoginPage';

/**
 * Replaces Web-server-configuration/02: once the web server leaves the port
 * 80, the legacy host form still saves, its internal API calls reaching the
 * right port. The port is not published: the browser reaches the container
 * on its docker network. The web server configuration is restored afterwards.
 */
const files = [
  '/etc/httpd/conf/httpd.conf',
  '/etc/httpd/conf.d/10-centreon.conf'
];
// 8080 is taken by the map server.
const port = 8555;

const reload = async (url: string) => {
  execInWebContainer('httpd -t && httpd -k graceful');
  await expect
    .poll(
      async () => {
        try {
          return (await fetch(url)).status;
        } catch {
          return 0;
        }
      },
      { timeout: 30_000 }
    )
    .toBeGreaterThan(0);
};

test('saves the legacy host form when the web server uses another port', async ({
  browser
}) => {
  test.setTimeout(180_000);
  // Router::generateLocalUrl() forces the port 80 for the internal API calls.
  test.fail(true, 'MON-198741: the legacy host form calls the API on port 80');
  const baseURL = `http://${webContainerAddress()}:${port}`;
  const name = `pw-port-${Date.now()}`;
  for (const file of files) {
    execInWebContainer(`cp ${file} ${file}.pw-backup`);
  }

  try {
    execInWebContainer(
      `sed -i "s/^Listen 80$/Listen ${port}/" ${files[0]} && sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" ${files[1]}`
    );
    await reload(`${baseURL}/centreon/`);

    const context = await browser.newContext({
      baseURL,
      storageState: { cookies: [], origins: [] }
    });
    try {
      const visitor = await context.newPage();
      await visitor.goto('/centreon/');
      await new LoginPage(visitor).login(adminUser);
      await expect(visitor).toHaveURL(/monitoring\/resources/, {
        timeout: 30_000
      });

      const hosts = new HostsPage(visitor);
      await hosts.open();
      await hosts.createHost({ name });
      await hosts.renameHost(name, `${name}-edited`);
      await expect(
        visitor
          .frameLocator('iframe#main-content')
          .getByText(/Failed to connect/)
      ).toHaveCount(0);
      await hosts.deleteHost(`${name}-edited`);
    } finally {
      await context.close();
    }
  } finally {
    for (const file of files) {
      execInWebContainer(`mv -f ${file}.pw-backup ${file}`);
    }
    await reload(`http://${webContainerAddress()}/centreon/`);
  }
});
