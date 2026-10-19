import { creatorStorageStatePath } from '../../fixtures/auth';
import { creationDashboards } from '../../fixtures/dashboards';
import { expect, test } from '../../fixtures/test';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';
import { DashboardCreationDialog } from '../../pages/DashboardFormDialog';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

const {
  requiredOnly,
  default: defaultDashboard,
  fromCreator
} = creationDashboards;

// Reuse the dashboard-creator session captured by the `setup` project instead
// of logging in through the UI in every test. `dashboardApi` is referenced for
// its automatic cleanup of dashboards created through the UI.
test.describe('Dashboard creation', () => {
  test.use({ storageState: creatorStorageStatePath });

  test('creates a dashboard with the required fields only', async ({
    page,
    dashboardApi
  }) => {
    void dashboardApi;
    const list = new DashboardsListPage(page);
    const dialog = new DashboardCreationDialog(page);
    const detail = new DashboardDetailPage(page);

    await list.open();
    await list.clickCreate();

    await dialog.expectVisible('Create dashboard');
    await expect(dialog.nameInput).toBeEmpty();
    await expect(dialog.descriptionTextarea).toBeEmpty();
    await expect(dialog.confirmButton).toBeDisabled();
    await expect(dialog.cancelButton).toBeEnabled();

    await dialog.setName(requiredOnly.name);
    await expect(dialog.nameInput).toHaveValue(requiredOnly.name);
    await expect(dialog.confirmButton).toBeEnabled();

    await dialog.confirm();

    await detail.expectOnDetailPage();
    await detail.expectInEditMode();
    await detail.expectTitle(requiredOnly.name);
  });

  test('creates a dashboard with a name and a description', async ({
    page,
    dashboardApi
  }) => {
    void dashboardApi;
    const list = new DashboardsListPage(page);
    const dialog = new DashboardCreationDialog(page);
    const detail = new DashboardDetailPage(page);

    await list.open();
    await list.clickCreate();

    await dialog.setName(defaultDashboard.name);
    await dialog.setDescription(defaultDashboard.description as string);
    await dialog.confirm();

    await detail.expectTitle(defaultDashboard.name);
    await detail.expectDescription(defaultDashboard.description as string);
  });

  test('cancels the creation form and keeps it empty on reopening', async ({
    page,
    dashboardApi
  }) => {
    void dashboardApi;
    const list = new DashboardsListPage(page);
    const dialog = new DashboardCreationDialog(page);

    await list.open();
    await list.clickCreate();
    await dialog.setName(`${defaultDashboard.name} to be cancelled`);
    await dialog.cancel();

    await expect(page).toHaveURL(/\/home\/dashboards\/library/);
    await list.expectNotVisible(`${defaultDashboard.name} to be cancelled`);

    await list.clickCreate();
    await expect(dialog.nameInput).toBeEmpty();
    await expect(dialog.descriptionTextarea).toBeEmpty();
  });

  test('creates a new dashboard while editing an existing one', async ({
    page,
    dashboardApi
  }) => {
    await dashboardApi.createDashboard(fromCreator);

    const list = new DashboardsListPage(page);
    const detail = new DashboardDetailPage(page);
    const dialog = new DashboardCreationDialog(page);

    await list.open();
    await list.openDashboard(fromCreator.name);
    await detail.enterEditMode();

    await detail.openQuickAccessCreate();
    await dialog.setName(defaultDashboard.name);
    await dialog.setDescription(defaultDashboard.description as string);
    await dialog.confirm();

    // Redirected to the brand-new (empty) dashboard, in edit mode.
    await detail.expectOnDetailPage();
    await detail.expectInEditMode();
    await detail.expectTitle(defaultDashboard.name);
  });
});
