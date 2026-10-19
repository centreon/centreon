import {
  labelAlias,
  labelConnect,
  labelLogin,
  labelPassword
} from '../../www/front_src/src/Login/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Main/Main: the application entry, with every bootstrap API mocked.
const storyId = 'Main/Main/Default';

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: './api/latest/platform/installation/status',
    response: { has_upgrade_available: false, is_installed: true }
  });
  await mockApi({
    endpoint: './api/latest/platform/versions',
    response: {
      modules: {},
      web: { fix: '0', major: '23', minor: '04', version: '23.04.1' },
      widgets: {}
    }
  });
  await mockApi({
    endpoint: './api/latest/configuration/users/current/parameters',
    response: { status: 403 },
    status: 403
  });
  await mockApi({
    endpoint: './api/latest/platform/features',
    response: { feature_flags: {}, is_cloud_platform: false }
  });
  await mockApi({
    endpoint: './api/external.php',
    response: { en: { hello: 'Hello' } }
  });
  await mockApi({
    endpoint: './api/latest/authentication/providers/configurations',
    response: [
      {
        authentication_uri:
          '/centreon/authentication/providers/configurations/local',
        id: 1,
        is_active: true,
        name: 'local'
      }
    ]
  });
});

test('displays the login page by default', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(component.getByText(labelLogin).first()).toBeVisible();
  await expect(component.getByLabel(labelAlias, { exact: true })).toBeVisible();
  await expect(
    component.getByLabel(labelPassword, { exact: true })
  ).toBeVisible();
  await expect(
    component.getByRole('button', { name: labelConnect })
  ).toBeVisible();

  await expectThemedScreenshot(component, 'main-login');
});

test('displays a public page without login', async ({ mount }) => {
  const component = await mount(storyId, {
    page: '/public/dashboards/playlists/hash'
  });

  await expect(component.getByText('Cannot load module')).toBeVisible();
  await expect(component.getByText(labelLogin)).toHaveCount(0);
});
