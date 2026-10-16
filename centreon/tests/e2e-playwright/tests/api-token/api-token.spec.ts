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
      // Idempotent: tolerate users already provisioned by a previous run (409),
      // but let a real provisioning failure surface here instead of cascading.
      await api.provision(tokenUsersActions, { tolerate: [409] });
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

  test('filters the listing by bound user', async ({ page, adminApi }) => {
    const [firstUser, secondUser] = tokenUsers;
    const firstUserId = await adminApi.findUserId(firstUser.alias);
    const secondUserId = await adminApi.findUserId(secondUser.alias);
    const tokenOfFirstUser = 'playwright-token-user-1';
    const tokenOfSecondUser = 'playwright-token-user-2';
    await adminApi.createToken({ name: tokenOfFirstUser, userId: firstUserId });
    await adminApi.createToken({
      name: tokenOfSecondUser,
      userId: secondUserId
    });

    const tokens = new ApiTokensPage(page);
    await tokens.open();
    await tokens.filterByUser(firstUser.alias);

    await expect(tokens.row(tokenOfFirstUser)).toBeVisible();
    await expect(tokens.row(tokenOfSecondUser)).toHaveCount(0);
  });

  test('filters the listing by creator', async ({ page, adminApi }) => {
    const userId = await adminApi.findUserId(tokenUser.alias);
    const name = 'playwright-token-creator';
    await adminApi.createToken({ name, userId });

    const tokens = new ApiTokensPage(page);
    await tokens.open();
    // Tokens seeded through the API are owned by the connected admin.
    await tokens.filterByCreator('admin admin');

    await expect(tokens.row(name)).toBeVisible();
  });

  // Mirrors the Cypress `03-api-token-sorting` outline: a first click on a
  // sortable column header sorts that column descending. The listing is
  // virtualized and mixes the platform's own poller/cma tokens with the seeded
  // ones, so we assert the request the UI issued (its `sort_by`) rather than the
  // rendered row order — the same approach the OpenId listing spec takes.
  test('sorts the listing by each column header in descending order', async ({
    page,
    adminApi
  }) => {
    const userId = await adminApi.findUserId(tokenUser.alias);
    await adminApi.createToken({ name: 'playwright-token-sort-1', userId });
    await adminApi.createToken({ name: 'playwright-token-sort-2', userId });

    const tokens = new ApiTokensPage(page);
    await tokens.open();

    for (const [label, field] of Object.entries(
      ApiTokensPage.columnSortField
    )) {
      const sort = await tokens.sortByColumn(label);
      expect(
        sort,
        `sorting by "${label}" requests ${field} descending`
      ).toEqual({ [field]: 'desc' });
    }
  });
});
