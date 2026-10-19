import { expect } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { adminUser } from '../../fixtures/credentials';
import { hostActions } from '../../fixtures/monitoring';
import {
  buildNotification,
  notificationHostGroupActions,
  notificationHostGroupName
} from '../../fixtures/notifications';
import { test } from '../../fixtures/test';
import { CentreonApi } from '../../helpers/CentreonApi';
import { enableNotificationFeature, ensureStack } from '../../helpers/docker';
import { NotificationsListPage } from '../../pages/NotificationsListPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

/**
 * Migration of the Cypress `Cloud-notifications/05-notification-listing`
 * feature: empty state + pagination. Notification rules are created through the
 * configuration API (one POST per rule) and removed before/after each test, the
 * Playwright equivalent of the Cypress `DELETE FROM notification` cleanup.
 */
test.describe('Cloud notifications listing', () => {
  test.use({ storageState: adminStorageStatePath });

  // The notification rules all target a single host group, created once.
  let hostGroupId: number;

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
    enableNotificationFeature();

    const api = await CentreonApi.create(baseURL);
    try {
      await api.authenticate(adminUser);
      // Idempotent: tolerate the host group/host already created by a previous
      // run (409); a real provisioning failure still surfaces here.
      await api.provision(
        [
          ...notificationHostGroupActions,
          ...hostActions({
            hostGroup: notificationHostGroupName,
            name: 'notification_host_1'
          })
        ],
        { tolerate: [409] }
      );
      hostGroupId = await api.findHostGroupId(notificationHostGroupName);
    } finally {
      await api.dispose();
    }
  });

  test.beforeEach(async ({ adminApi }) => {
    await adminApi.deleteAllNotifications();
  });

  test.afterEach(async ({ adminApi }) => {
    await adminApi.deleteAllNotifications();
  });

  test('shows the empty state and disables pagination without any rule', async ({
    page
  }) => {
    const notifications = new NotificationsListPage(page);

    await notifications.open();

    await expect(notifications.noResult).toBeVisible();
    await expect(notifications.previousPageButton).toBeDisabled();
    await expect(notifications.nextPageButton).toBeDisabled();
  });

  test('paginates rules across pages', async ({ page, adminApi }) => {
    const ruleCount = 15;
    for (let index = 1; index <= ruleCount; index += 1) {
      await adminApi.createNotification(
        buildNotification(`Notification Created ${index}`, hostGroupId)
      );
    }

    const notifications = new NotificationsListPage(page);

    await notifications.open();
    await notifications.setRowsPerPage(10);

    expect(await notifications.totalCount()).toBe(ruleCount);

    // First page: no previous, a next page is available.
    await expect(notifications.previousPageButton).toBeDisabled();
    await expect(notifications.nextPageButton).toBeEnabled();

    // Second (last) page: previous becomes available, next is exhausted.
    await notifications.goToNextPage();
    await expect(notifications.previousPageButton).toBeEnabled();
    await expect(notifications.nextPageButton).toBeDisabled();
  });
});
