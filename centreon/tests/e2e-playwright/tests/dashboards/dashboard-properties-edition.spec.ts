import { creatorStorageStatePath } from '../../fixtures/auth';
import { dashboardToEdit, seededDashboards } from '../../fixtures/dashboards';
import { expect, test } from '../../fixtures/test';
import { DashboardPropertiesDialog } from '../../pages/DashboardFormDialog';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

const editedName = 'dashboard-edited';
const editedDescription = 'dashboard-edited';

test.describe('Dashboard properties edition', () => {
  test.use({ storageState: creatorStorageStatePath });

  test.beforeEach(async ({ dashboardApi }) => {
    await dashboardApi.createDashboards(seededDashboards);
  });

  test('updates a dashboard name and description', async ({ page }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DashboardPropertiesDialog(page);

    await list.open();
    await list.openProperties(dashboardToEdit.name);

    await dialog.expectVisible('Update dashboard');
    await expect(dialog.nameInput).toHaveValue(dashboardToEdit.name);
    await expect(dialog.descriptionTextarea).toHaveValue(
      dashboardToEdit.description as string
    );
    await expect(dialog.confirmButton).toBeDisabled();
    await expect(dialog.cancelButton).toBeEnabled();

    await dialog.setName(editedName);
    await dialog.setDescription(editedDescription);
    await expect(dialog.confirmButton).toBeEnabled();

    await dialog.confirm();

    await list.expectNotVisible(dashboardToEdit.name);
    await list.expectVisible(editedName);
  });

  test('discards changes when the update form is cancelled', async ({
    page
  }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DashboardPropertiesDialog(page);

    await list.open();
    await list.openProperties(dashboardToEdit.name);
    await dialog.setName('dashboard-cancel-update-changes');
    await dialog.setDescription('dashboard-cancel-update-changes');
    await dialog.cancel();

    await list.expectNotVisible('dashboard-cancel-update-changes');
    await list.expectVisible(dashboardToEdit.name);

    await list.openProperties(dashboardToEdit.name);
    await expect(dialog.nameInput).not.toHaveValue(
      'dashboard-cancel-update-changes'
    );
    await expect(dialog.descriptionTextarea).not.toHaveValue(
      'dashboard-cancel-update-changes'
    );
  });

  test('prevents saving with an empty name', async ({ page }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DashboardPropertiesDialog(page);

    await list.open();
    await list.openProperties(dashboardToEdit.name);

    await dialog.clearName();
    await expect(dialog.confirmButton).toBeDisabled();

    await dialog.setName('dashboard-update-name');
    await expect(dialog.confirmButton).toBeEnabled();
  });

  test('allows saving with an empty description', async ({ page }) => {
    const list = new DashboardsListPage(page);
    const dialog = new DashboardPropertiesDialog(page);

    await list.open();
    await list.openProperties(dashboardToEdit.name);

    await dialog.clearDescription();
    await expect(dialog.confirmButton).toBeEnabled();

    await dialog.confirm();

    await list.expectVisible(dashboardToEdit.name);
  });
});
