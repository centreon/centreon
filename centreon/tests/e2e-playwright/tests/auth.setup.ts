import { test as setup } from '@playwright/test';

import {
  adminStorageStatePath,
  creatorStorageStatePath
} from '../fixtures/auth';
import { adminUser, dashboardCreatorUser } from '../fixtures/credentials';
import { LoginPage } from '../pages/LoginPage';

/**
 * Authentication setup project: log in once as the dashboard-creator user and
 * persist the browser session. The dashboard specs reuse it via
 * `test.use({ storageState })`, avoiding a UI login in every test.
 *
 * The user is provisioned by `global-setup.ts`, which runs before this project.
 */
setup('authenticate as the dashboard creator', async ({ page }) => {
  const loginPage = new LoginPage(page);
  await loginPage.open();
  await loginPage.login(dashboardCreatorUser);
  // The login form unmounts once authenticated (language-independent signal).
  await loginPage.aliasInput.waitFor({ state: 'detached' });

  await page.context().storageState({ path: creatorStorageStatePath });
});

/**
 * Authentication setup project: log in once as the admin user and persist the
 * browser session. The resources-status and cloud-notifications specs reuse it
 * via `test.use({ storageState })`, avoiding a UI login in every test.
 */
setup('authenticate as the admin', async ({ page }) => {
  const loginPage = new LoginPage(page);
  await loginPage.open();
  await loginPage.login(adminUser);
  await loginPage.aliasInput.waitFor({ state: 'detached' });

  await page.context().storageState({ path: adminStorageStatePath });
});
