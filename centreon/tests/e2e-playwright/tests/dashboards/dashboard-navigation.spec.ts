import { creatorStorageStatePath } from '../../fixtures/auth';
import { dashboardToLocate, seededDashboards } from '../../fixtures/dashboards';
import { expect, test } from '../../fixtures/test';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

test.describe('Dashboard navigation', () => {
  test.use({ storageState: creatorStorageStatePath });

  test('shows an empty state with a create button when no dashboard exists', async ({
    page,
    dashboardApi
  }) => {
    void dashboardApi;
    const list = new DashboardsListPage(page);

    await list.open();

    await list.expectEmptyState();
  });

  test('opens a dashboard detail page when its card is clicked', async ({
    page,
    dashboardApi
  }) => {
    await dashboardApi.createDashboards(seededDashboards);

    const list = new DashboardsListPage(page);
    const detail = new DashboardDetailPage(page);

    await list.open();
    await list.openDashboard(dashboardToLocate.name);

    await detail.expectOnDetailPage();
    await detail.expectTitle(dashboardToLocate.name);
  });
});
