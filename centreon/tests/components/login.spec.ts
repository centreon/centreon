import defaultLoginPageCustomization from '../../cypress/fixtures/login/defaultLoginPageCustomization.json';
import loginPageCustomization from '../../cypress/fixtures/login/loginPageCustomization.json';
import {
  labelAlias,
  labelAnErrorOccurredDuringAuthentication,
  labelCentreonLogo,
  labelCentreonWallpaper,
  labelConnect,
  labelDisplayThePassword,
  labelHideThePassword,
  labelLoginSucceeded,
  labelLoginWith,
  labelPassword,
  labelPasswordHasExpired,
  labelPoweredByCentreon,
  labelRequired
} from '../../www/front_src/src/Login/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Login/Login; useNavigate stubs become LocationRecorder checks.
const storyId = 'Login/Login/Default';
const loginEndpoint =
  './api/latest/authentication/providers/configurations/local';
const customizationEndpoint = './api/latest/it-edition-extensions/login_page';
const openidUri = '/centreon/authentication/providers/configurations/openid';

const provider = (id: number, name: string, extra = {}) => ({
  authentication_uri: `/centreon/authentication/providers/configurations/${name}`,
  id,
  is_active: true,
  name,
  ...extra
});

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ mockApi, page }) => {
  await page.clock.setFixedTime(new Date('2020-01-01'));
  await mockApi({
    endpoint: './api/external.php',
    response: { en: { hello: 'Hello' } }
  });
  await mockApi({
    endpoint: './api/latest/authentication/providers/configurations',
    response: [
      provider(1, 'local'),
      provider(2, 'openid'),
      provider(3, 'ldap', { is_active: false })
    ]
  });
  await mockApi({
    endpoint: './api/latest/configuration/users/current/parameters',
    response: {
      alias: 'Admin alias',
      default_page: '/monitoring/resources',
      is_export_button_enabled: true,
      locale: 'fr_FR.UTF8',
      name: 'Admin',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false
    }
  });
});

const field = (component: import('@playwright/test').Locator, label: string) =>
  component.getByLabel(label, { exact: true });

const expectLoginForm = async (
  component: import('@playwright/test').Locator
): Promise<void> => {
  await expect(field(component, labelAlias)).toBeVisible();
  await expect(field(component, labelPassword)).toBeVisible();
  await expect(
    component.getByRole('button', { name: labelConnect })
  ).toBeVisible();
  await expect(component.getByText(labelPoweredByCentreon)).toBeVisible();
  await expect(component.getByText('v. 21.10.1')).toBeVisible();
  await expect(
    component.getByLabel(`${labelLoginWith} openid`)
  ).toHaveAttribute('href', openidUri);
};

test.describe('Login page', () => {
  test('displays the login form and the providers', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);

    await expect(component.getByAltText(labelCentreonLogo)).toBeVisible();
    await expect(component.getByAltText(labelCentreonWallpaper)).toBeVisible();
    await expectLoginForm(component);

    await expectThemedScreenshot(component, 'login');
  });

  test('logs in with valid credentials', async ({ mockApi, mount, page }) => {
    const login = await mockApi({
      endpoint: loginEndpoint,
      method: 'POST',
      response: { redirect_uri: '/monitoring/resources' }
    });
    const component = await mount(storyId);

    await field(component, labelAlias).fill('admin');
    await field(component, labelPassword).fill('centreon');
    await component.getByRole('button', { name: labelConnect }).click();

    await expect(page.getByText(labelLoginSucceeded)).toBeVisible();
    await expect(component.getByTestId('location')).toHaveValue(
      '/monitoring/resources'
    );
    expect(login.bodies).toEqual([{ login: 'admin', password: 'centreon' }]);
  });

  const failures = [
    {
      message: 'Invalid credentials',
      name: 'rejects invalid credentials',
      response: { code: 401, message: 'Invalid credentials' },
      status: 401
    },
    {
      message: 'This is an error from the server',
      name: 'stays on the page after a server error',
      response: { message: 'This is an error from the server' },
      status: 500
    }
  ];

  for (const { message, name, response, status } of failures) {
    test(name, async ({ mockApi, mount, page }) => {
      const login = await mockApi({
        endpoint: loginEndpoint,
        method: 'POST',
        response,
        status
      });
      const component = await mount(storyId);

      await field(component, labelAlias).fill('invalid_alias');
      await field(component, labelPassword).fill('invalid_password');
      await component.getByRole('button', { name: labelConnect }).click();

      await expect(page.getByText(message)).toBeVisible();
      await expect(component.getByTestId('location')).toHaveValue('/login');
      await expect(field(component, labelAlias)).toBeVisible();
      expect(login.bodies).toEqual([
        { login: 'invalid_alias', password: 'invalid_password' }
      ]);
    });
  }

  test('requires both fields', async ({ mount }) => {
    const component = await mount(storyId);
    const connect = component.getByRole('button', { name: labelConnect });

    await expect(connect).toBeDisabled();
    await field(component, labelAlias).fill('admin');
    await field(component, labelPassword).fill('centreon');
    await expect(connect).toBeEnabled();

    await field(component, labelAlias).clear();
    await field(component, labelPassword).clear();

    await expect(connect).toBeDisabled();
    await expect(component.getByText(labelRequired)).toHaveCount(2);
  });

  test('shows and hides the password', async ({ mount }) => {
    const component = await mount(storyId);
    const password = field(component, labelPassword);

    await password.fill('password');
    await expect(password).toHaveAttribute('type', 'password');

    await component.getByLabel(labelDisplayThePassword).click();
    await expect(password).toHaveAttribute('type', 'text');

    await component.getByLabel(labelHideThePassword).click();
    await expect(password).toHaveAttribute('type', 'password');
  });

  test('sends an expired password to the reset page', async ({
    mockApi,
    mount,
    page
  }) => {
    await mockApi({
      endpoint: loginEndpoint,
      method: 'POST',
      response: {
        password_is_expired: true,
        redirect_uri: '/monitoring/resources'
      },
      status: 401
    });
    const component = await mount(storyId);

    await field(component, labelAlias).fill('admin');
    await field(component, labelPassword).fill('centreon');
    await component.getByRole('button', { name: labelConnect }).click();

    await expect(page.getByText(labelPasswordHasExpired)).toBeVisible();
    await expect(component.getByTestId('location')).toHaveValue(
      '/reset-password'
    );
  });

  test('displays the error of a forced provider', async ({
    mockApi,
    mount
  }) => {
    await mockApi({
      endpoint: './api/latest/authentication/providers/configurations',
      response: [
        provider(1, 'local'),
        provider(2, 'openid', { is_forced: true })
      ]
    });

    const component = await mount(storyId, {
      search: '?authenticationError=An%20error%20occurred'
    });

    await expect(
      component.getByText(labelAnErrorOccurredDuringAuthentication)
    ).toBeVisible();
    await expect(
      component.getByText('An error occurred', { exact: true })
    ).toBeVisible();
  });
});

const customizations = [
  {
    customization: defaultLoginPageCustomization,
    headerItems: 1,
    name: 'the default customization',
    preview: false
  },
  {
    customization: loginPageCustomization,
    headerItems: 2,
    name: 'a customization',
    preview: true
  }
];

for (const { customization, headerItems, name, preview } of customizations) {
  test(`displays the IT edition login page with ${name}`, async ({
    mockApi,
    mount
  }) => {
    await mockApi({ endpoint: customizationEndpoint, response: customization });
    const component = await mount(storyId, { hasItEditionInstalled: true });

    await expectLoginForm(component);
    await expect(component.locator('#loginHeader > *')).toHaveCount(
      headerItems
    );
    await expect(component.locator('#Previewtop')).toHaveCount(0);
    if (preview) {
      await expect(
        component.getByText('Gendarmerie de la Haute-Garonne')
      ).toBeVisible();
      await expect(component.getByLabel('Previewbottom')).toContainText(
        'centreon'
      );
    } else {
      await expect(component.locator('#Previewbottom')).toHaveCount(0);
    }
  });
}

test('displays the login page without the IT edition', async ({ mount }) => {
  const component = await mount(storyId);

  await expectLoginForm(component);
  await expect(component.locator('#loginHeader > *')).toHaveCount(1);
  await expect(component.locator('#Previewtop')).toHaveCount(0);
  await expect(component.locator('#Previewbottom')).toHaveCount(0);
});
