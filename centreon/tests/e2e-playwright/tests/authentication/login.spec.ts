import { expect, test } from '@playwright/test';

import { adminUser, invalidUser } from '../../fixtures/credentials';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';

/**
 * Authentication end-to-end tests, driven entirely through Page Objects.
 *
 * Runs against the Centreon docker compose stack (`web` service) exposed on
 * http://localhost:4000/centreon.
 */
test.describe('Authentication', () => {
  test('logs in with valid credentials and reaches the default page', async ({
    page
  }) => {
    const loginPage = new LoginPage(page);
    const header = new MainHeader(page);

    await loginPage.open();
    await loginPage.login(adminUser);

    // The default landing page for a fresh platform is the resources monitoring.
    await expect(page).toHaveURL(/\/monitoring\/resources/);
    await header.expectLoaded();
  });

  test('rejects invalid credentials and stays on the login page', async ({
    page
  }) => {
    const loginPage = new LoginPage(page);

    await loginPage.open();
    await loginPage.login(invalidUser);

    expect(await loginPage.getErrorMessage()).toMatch(/Authentication failed/i);
    await loginPage.expectVisible();
  });

  test('logs out and returns to the login page', async ({ page }) => {
    const loginPage = new LoginPage(page);
    const header = new MainHeader(page);

    await loginPage.open();
    await loginPage.login(adminUser);
    await header.expectLoaded();

    await header.logout();

    await expect(page).toHaveURL(/\/login/);
    await loginPage.expectVisible();
  });
});
