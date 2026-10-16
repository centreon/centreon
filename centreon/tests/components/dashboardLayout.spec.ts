import dashboardDetails from '../../cypress/fixtures/Dashboards/Dashboard/details.json';
import { labelEditDashboard } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Dashboards/SingleInstancePage/Dashboard/DashboardLayout.
const storyId = 'Dashboard/DashboardLayout/Default';

test('displays a dashboard from a standalone component', async ({
  expectThemedScreenshot,
  mockApi,
  mount
}) => {
  const details = await mockApi({
    endpoint: './api/latest/configuration/dashboards/1',
    response: { ...dashboardDetails, own_role: 'viewer' }
  });

  const component = await mount(storyId);

  await expect(component.getByText('Widget text')).toBeVisible();
  await expect(component.getByText('Generic text')).toBeVisible();
  await expect(component.getByText(labelEditDashboard)).toHaveCount(0);
  expect(details.urls).not.toHaveLength(0);

  await expectThemedScreenshot(component, 'dashboard-layout');
});

test('reads a public dashboard from the public API', async ({
  mockApi,
  mount
}) => {
  const publicDetails = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1',
    response: dashboardDetails
  });

  await mount(storyId, { isPublic: true });

  await expect.poll(() => publicDetails.urls.length).toBeGreaterThan(0);
});
