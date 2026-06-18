import { test as base } from '@playwright/test';

import { CentreonApi } from '../helpers/CentreonApi';
import { adminUser, dashboardCreatorUser } from './credentials';

interface CentreonFixtures {
  /**
   * API client authenticated as the dashboard-creator user, used to seed
   * dashboards. Every dashboard it can see is removed before and after each
   * test, replacing the Cypress `beforeEach`/`afterEach` DB cleanup.
   */
  dashboardApi: CentreonApi;
  /**
   * API client authenticated as admin (v2 session + v1 token), used to seed
   * monitoring objects, push passive results and manage notification rules.
   */
  adminApi: CentreonApi;
}

const baseUrl = (url?: string): string =>
  url ?? 'http://localhost:4000/centreon';

export const test = base.extend<CentreonFixtures>({
  adminApi: async ({ baseURL }, use) => {
    const api = await CentreonApi.create(baseUrl(baseURL));
    await api.authenticate(adminUser);
    await use(api);
    await api.dispose();
  },
  dashboardApi: async ({ baseURL }, use) => {
    const api = await CentreonApi.create(baseUrl(baseURL));
    await api.login(dashboardCreatorUser);
    // Guarantee a clean slate before and after each test.
    await api.deleteAllDashboards();
    await use(api);
    await api.deleteAllDashboards();
    await api.dispose();
  }
});

export { expect } from '@playwright/test';
