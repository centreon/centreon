import loginPageCustomization from '../../cypress/fixtures/login/loginPageCustomization.json';
import {
  labelCentreonLogo,
  labelCentreonWallpaper,
  labelPoweredByCentreon
} from '../../www/front_src/src/Login/translatedLabels';
import {
  labelCurrentPassword,
  labelNewPassword,
  labelNewPasswordConfirmation,
  labelNewPasswordsMustMatch,
  labelResetPassword,
  labelTheNewPasswordIstheSameAsTheOldPassword
} from '../../www/front_src/src/ResetPassword/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from ResetPassword/ResetPassword.
const storyId = 'ResetPassword/ResetPassword/Default';

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: './api/latest/authentication/providers/configurations/local',
    method: 'POST',
    response: { redirect_uri: '/monitoring/resources' }
  });
  await mockApi({
    endpoint: './api/latest/configuration/users/current/parameters',
    response: {
      alias: 'Admin',
      default_page: '/monitoring/resources',
      isExportButtonEnabled: true,
      locale: 'fr_FR.UTF8',
      name: 'Admin',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false
    }
  });
  await mockApi({
    endpoint: './api/latest/it-edition-extensions/login_page',
    response: loginPageCustomization
  });
});

test('displays the reset password form', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(component.getByText(labelResetPassword).first()).toBeVisible();
  for (const label of [
    labelCurrentPassword,
    labelNewPassword,
    labelNewPasswordConfirmation
  ]) {
    await expect(component.getByLabel(label, { exact: true })).toBeVisible();
  }
  await expect(component.getByAltText(labelCentreonLogo)).toBeVisible();

  await expectThemedScreenshot(component, 'reset-password');
});

test('rejects a reused or mismatching password', async ({ mount }) => {
  const component = await mount(storyId);
  const field = (label: string) => component.getByLabel(label, { exact: true });
  const submit = component.getByRole('button', { name: labelResetPassword });

  await field(labelCurrentPassword).fill('current-password');
  await field(labelNewPassword).fill('current-password');
  await field(labelNewPasswordConfirmation).click();
  await expect(
    component.getByText(labelTheNewPasswordIstheSameAsTheOldPassword)
  ).toBeVisible();
  await expect(submit).toBeDisabled();

  await field(labelNewPassword).fill('new-password');
  await field(labelNewPasswordConfirmation).fill('new-password-2');
  await field(labelNewPassword).click();
  await expect(component.getByText(labelNewPasswordsMustMatch)).toBeVisible();
  await expect(submit).toBeDisabled();
});

test('sends back to the login page without reset information', async ({
  mount
}) => {
  const component = await mount(storyId, { alias: null });

  await expect(component.getByTestId('location')).toHaveValue('/login');
});

test('renews the password and opens the default page', async ({
  mockApi,
  mount
}) => {
  const reset = await mockApi({
    endpoint: './api/latest/authentication/users/admin/password',
    method: 'PUT',
    status: 201
  });
  const component = await mount(storyId);
  const field = (label: string) => component.getByLabel(label, { exact: true });

  await field(labelCurrentPassword).fill('current-password');
  await field(labelNewPassword).fill('new-password');
  await field(labelNewPasswordConfirmation).fill('new-password');
  await component.getByRole('button', { name: labelResetPassword }).click();

  await expect(component.getByTestId('location')).toHaveValue(
    '/monitoring/resources'
  );
  expect(reset.bodies).toEqual([
    { new_password: 'new-password', old_password: 'current-password' }
  ]);
});

test('displays the customized page of the IT edition', async ({ mount }) => {
  const component = await mount(storyId, { hasItEditionInstalled: true });

  await expect(component.getByTestId(labelCentreonLogo)).toBeVisible();
  await expect(component.getByTestId(labelCentreonWallpaper)).toBeVisible();
  await expect(component.getByText(labelPoweredByCentreon)).toBeVisible();
  await expect(component.getByText('v. 21.10.1')).toBeVisible();
  await expect(
    component.getByText('Gendarmerie de la Haute-Garonne')
  ).toBeVisible();
  await expect(component.locator('#Previewtop')).toHaveCount(0);
  await expect(component.getByLabel('Previewbottom')).toContainText('centreon');
});
