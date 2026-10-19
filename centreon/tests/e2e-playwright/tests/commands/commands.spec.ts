import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { CommandsPage } from '../../pages/CommandsPage';

/**
 * Migration of the Cypress `Commands` feature (create / delete happy paths).
 *
 * The commands page is a modern React configuration page backed by the REST API
 * (`/api/latest/configuration/commands`): no monitoring data and no engine
 * reload, so the flow is fast and reliable. Created commands are deleted through
 * the UI under test, and a best-effort CLAPI `DEL CMD` cleanup in afterEach keeps
 * reruns idempotent even if a UI step fails midway.
 *
 * Skipped from the Cypress source (out of scope): modify/duplicate scenarios,
 * the notification/discovery `Scenario Outline` (relies on connector autocomplete
 * popovers), and the legacy host/service "command arguments" scenarios (iframe +
 * extra monitoring config).
 */
test.describe('Commands configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  // Unique-ish names so concurrent/rerun executions do not collide.
  const createName = `pw-check-cmd-${Date.now()}`;
  const deleteName = `pw-delete-cmd-${Date.now()}`;

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test.afterEach(async ({ adminApi }) => {
    // Best-effort cleanup: remove any command the test may have left behind.
    for (const name of [createName, deleteName]) {
      try {
        await adminApi.provision([
          { action: 'DEL', object: 'CMD', values: name }
        ]);
      } catch {
        // Command already deleted by the test or never created.
      }
    }
  });

  test('creates a command and shows it in the list', async ({ page }) => {
    const commands = new CommandsPage(page);

    await commands.open();
    await commands.createCommand({ name: createName, type: 'Check' });

    await commands.searchByName(createName);
    await expect(commands.row(createName)).toBeVisible();
  });

  test('deletes a command and removes it from the list', async ({
    page,
    adminApi
  }) => {
    // Seed the command to delete through CLAPI so the test is independent.
    await adminApi.provision([
      {
        action: 'ADD',
        object: 'CMD',
        values: `${deleteName};check;$CENTREONPLUGINS$/check_dummy`
      }
    ]);

    const commands = new CommandsPage(page);
    await commands.open();
    await commands.searchByName(deleteName);
    await expect(commands.row(deleteName)).toBeVisible();

    await commands.deleteCommand(deleteName);

    await commands.searchByName(deleteName);
    await expect(commands.row(deleteName)).toHaveCount(0);
  });
});
