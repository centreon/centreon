import { test as base } from '@playwright/test';

import { LoginPage } from '../pages/LoginPage';
import { ResourcesStatusPage } from '../pages/ResourcesStatusPage';

interface Pages {
  loginPage: LoginPage;
  resourcesStatusPage: ResourcesStatusPage;
}

/**
 * Page objects are injected as fixtures: specs declare the pages they need and
 * never instantiate them, so a page object signature change stays local.
 */
export const test = base.extend<Pages>({
  loginPage: async ({ page }, use) => {
    await use(new LoginPage(page));
  },
  resourcesStatusPage: async ({ page }, use) => {
    await use(new ResourcesStatusPage(page));
  }
});

export { expect } from '@playwright/test';
