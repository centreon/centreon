import { expect, test } from '../../fixtures/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { ensureStack } from '../../helpers/docker';
import { CustomViewsPage } from '../../pages/CustomViewsPage';

/**
 * Adaptation of the Cypress `Custom-views` feature, covering the reliable
 * single-user core: create a custom view and delete it. (The public/unlocked/
 * locked *sharing* scenarios need several provisioned users + ACL and the
 * sharing UI; they are out of scope for this slice.)
 *
 * Like the proxy spec, the page is a legacy PHP page rendered in the React
 * shell's `#main-content` iframe, driven through a Playwright frame locator —
 * showing legacy iframe pages are migratable.
 */
test.describe('Custom views', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test('creates a custom view and deletes it', async ({ page }) => {
    const customViews = new CustomViewsPage(page);
    const viewName = 'pw-custom-view';

    await customViews.open();
    await customViews.enterEditMode();

    await customViews.addView(viewName);
    await expect(customViews.viewTab(viewName)).toBeVisible();

    await customViews.deleteCurrentView();
    await expect(customViews.viewTab(viewName)).toHaveCount(0);
  });
});
