import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { adminUser } from '../../fixtures/credentials';
import { test } from '../../fixtures/test';
import { tokenUsers, tokenUsersActions } from '../../fixtures/tokens';
import { CentreonApi } from '../../helpers/CentreonApi';
import { ensureStack } from '../../helpers/docker';
import { ApiTokensPage } from '../../pages/ApiTokensPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

const [tokenUser] = tokenUsers;

/**
 * Migration of the Cypress `Api-Token` feature (create / delete / filter).
 *
 * This is pure configuration UI + REST API: no monitoring data, no engine
 * reload, so it is fast and reliable. Tokens used as fixtures are seeded through
 * the API; only the flow under test goes through the UI. The clipboard
 * permission is granted so the copy step works headless.
 */
test.describe('API tokens', () => {
  test.use({
    permissions: ['clipboard-read', 'clipboard-write'],
    storageState: adminStorageStatePath
  });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
    const api = await CentreonApi.create(baseURL);
    try {
      await api.authenticate(adminUser);
      try {
        await api.provision(tokenUsersActions);
      } catch {
        // users already provisioned by a previous run
      }
    } finally {
      await api.dispose();
    }
  });

  // Only ever remove API-type tokens, leaving the platform's poller/cma tokens.
  test.beforeEach(async ({ adminApi }) => {
    await adminApi.deleteAllApiTokens();
  });

  test.afterEach(async ({ adminApi }) => {
    await adminApi.deleteAllApiTokens();
  });

  test('creates an API token then reveals and copies it', async ({ page }) => {
    const tokens = new ApiTokensPage(page);

    await tokens.open();
    await tokens.createToken({
      duration: '30 days',
      name: 'playwright-created-token',
      userAlias: tokenUser.alias
    });

    await tokens.revealAndCopyToken();
    await expect(
      tokens.snackbar('Authentication token copied to the clipboard')
    ).toBeVisible();
  });

  test('deletes a token after confirmation', async ({ page, adminApi }) => {
    const name = 'playwright-token-to-delete';
    const userId = await adminApi.findUserId(tokenUser.alias);
    await adminApi.createToken({ name, userId });

    const tokens = new ApiTokensPage(page);
    await tokens.open();
    await expect(tokens.row(name)).toBeVisible();

    await tokens.deleteToken(name, { confirm: true });
    await expect(tokens.row(name)).toHaveCount(0);
  });

  test('keeps the token when the deletion is cancelled', async ({
    page,
    adminApi
  }) => {
    const name = 'playwright-token-to-keep';
    const userId = await adminApi.findUserId(tokenUser.alias);
    await adminApi.createToken({ name, userId });

    const tokens = new ApiTokensPage(page);
    await tokens.open();

    await tokens.deleteToken(name, { confirm: false });
    await expect(tokens.row(name)).toBeVisible();
  });

  test('filters the listing by token name', async ({ page, adminApi }) => {
    const userId = await adminApi.findUserId(tokenUser.alias);
    const matching = 'playwright-token-alpha';
    const other = 'playwright-token-beta';
    await adminApi.createToken({ name: matching, userId });
    await adminApi.createToken({ name: other, userId });

    const tokens = new ApiTokensPage(page);
    await tokens.open();
    await tokens.filterByName(matching);

    await expect(tokens.row(matching)).toBeVisible();
    await expect(tokens.row(other)).toHaveCount(0);
  });
});
