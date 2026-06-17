import { test as base } from '@playwright/test';

import { CentreonApi } from '../helpers/CentreonApi';

import { dashboardCreatorUser } from './credentials';

interface CentreonFixtures {
  /**
   * API client authenticated as the dashboard-creator user, used to seed
   * dashboards. Every dashboard it can see is removed before and after each
   * test, replacing the Cypress `beforeEach`/`afterEach` DB cleanup.
   */
  dashboardApi: CentreonApi;
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
  }
});

export { expect } from '@playwright/test';
