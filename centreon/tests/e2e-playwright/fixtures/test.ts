import { test as base } from '@playwright/test';

import { CentreonApi } from '../helpers/CentreonApi';
import { LoginPage } from '../pages/LoginPage';
import { dashboardCreatorUser } from './credentials';

interface CentreonFixtures {
  /**
   * API client authenticated as the dashboard-creator user, used to seed
   * dashboards. Every dashboard it can see is removed after the test, replacing
   * the Cypress `afterEach` DB cleanup.
   */
  dashboardApi: CentreonApi;
  /** Log in through the UI as the dashboard-creator user. */
  loginAsCreator: () => Promise<void>;
}

export const test = base.extend<CentreonFixtures>({
  dashboardApi: async ({ baseURL }, use) => {
    const api = await CentreonApi.create(
      baseURL ?? 'http://localhost:4000/centreon'
    );
    await api.login(dashboardCreatorUser);
    // Guarantee a clean slate before and after each test.
    await api.deleteAllDashboards();
    await use(api);
    await api.deleteAllDashboards();
    await api.dispose();
  },
  loginAsCreator: async ({ page }, use) => {
    const loginAsCreator = async (): Promise<void> => {
      const loginPage = new LoginPage(page);
      await loginPage.open();
      await loginPage.login(dashboardCreatorUser);
      // The creator user has a restricted ACL, so the landing page is not
      // necessarily /monitoring/resources. Wait for the login form to unmount,
      // which is a language-independent "logged in" signal.
      await loginPage.aliasInput.waitFor({ state: 'detached' });
    };
    await use(loginAsCreator);
  }
});

export { expect } from '@playwright/test';
