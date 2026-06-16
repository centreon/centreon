import { dashboardToDelete, seededDashboards } from '../../fixtures/dashboards';
import { test } from '../../fixtures/test';
import { DashboardsListPage } from '../../pages/DashboardsListPage';
import { DeleteDashboardDialog } from '../../pages/DeleteDashboardDialog';

test.describe('Dashboard deletion', () => {
  test.beforeEach(async ({ loginAsCreator, dashboardApi }) => {
    await dashboardApi.createDashboards(seededDashboards);
    await loginAsCreator();
  });

  test('deletes a dashboard after confirmation', async ({ page }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DeleteDashboardDialog(page);

    await list.open();
    await list.openDelete(dashboardToDelete.name);

    await dialog.expectVisibleFor(dashboardToDelete.name);
    await dialog.confirm();

    await list.expectNotVisible(dashboardToDelete.name);
  });

  test('keeps the dashboard when the deletion is cancelled', async ({
    page
  }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DeleteDashboardDialog(page);

    await list.open();
    await list.openDelete(dashboardToDelete.name);
    await dialog.cancel();

    await list.expectVisible(dashboardToDelete.name);
  });
});
