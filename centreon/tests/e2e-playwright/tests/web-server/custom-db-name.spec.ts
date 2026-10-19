import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { recreateStack } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { PollerConfigurationPage } from '../../pages/PollerConfigurationPage';

/**
 * Replaces Web-server-configuration/03: a platform installed with databases
 * not named centreon and centreon_storage exports and reloads the central
 * poller configuration. It reinstalls the stack, so it has a shard of its own
 * (`web-server`); outside CI, the default stack is installed back afterwards.
 */
test.beforeAll(async () => {
  test.setTimeout(900_000);
  await recreateStack({
    MYSQL_DB_CONFIGURATION: 'quality-centreon_config',
    MYSQL_DB_STORAGE: 'quality-centreon_storage'
  });
});

test.afterAll(async () => {
  test.setTimeout(900_000);
  if (!process.env.CI) {
    await recreateStack();
  }
});

test('exports and reloads the configuration with custom database names', async ({
  page
}) => {
  await signIn(page, adminUser);
  const pollers = new PollerConfigurationPage(page);
  await pollers.open();
  await pollers.openPollerMenu();
  await pollers.exportAndReloadAllPollers();
  await expect(
    pollers.snackbar('Configuration exported and reloaded')
  ).toBeVisible({
    timeout: 60_000
  });
});
