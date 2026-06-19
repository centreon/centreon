import { expect, test } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { ensureStack } from '../../helpers/docker';
import { ProxyConfigurationPage } from '../../pages/ProxyConfigurationPage';

/**
 * Migration of the Cypress `Administration/03-proxy-configuration` "successful
 * connection" scenario.
 *
 * The legacy "Centreon UI" parameters page (a PHP form embedded in the React
 * shell's `#main-content` iframe) lets an admin test the proxy: the backend
 * reaches `api.imp.centreon.com` through the configured proxy and reports the
 * outcome in a popin. We point the proxy at the `squid-simple` container (a real
 * working forward proxy from the compose stack) and assert the success message.
 *
 * Demonstrates that legacy iframe pages are migratable to Playwright via a frame
 * locator. Requires outbound connectivity to the Centreon IMP servers (the web
 * container has it; so do CI runners).
 */
test.describe('Proxy configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  // Bring up the forward-proxy container alongside the web stack.
  test.beforeAll(async () => {
    await ensureStack({
      profiles: ['squid-simple'],
      services: ['web', 'squid-simple']
    });
  });

  test('reports a successful connection through the squid proxy', async ({
    page
  }) => {
    const proxy = new ProxyConfigurationPage(page);

    await proxy.open();
    await proxy.setProxy('squid-simple', '3128');
    await proxy.testConnection();

    await expect(proxy.successMessage).toBeVisible({ timeout: 30_000 });
    await expect(proxy.successMessage).toContainText('Connection Successful');
  });
});
