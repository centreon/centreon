import { defaultPasswordSecurityPolicyAPI } from '../../www/front_src/src/Authentication/Local/defaults';
import {
  labelDoYouWantToResetTheForm,
  labelReset,
  labelResetTheForm,
  labelSave
} from '../../www/front_src/src/Authentication/Local/translatedLabels';
import { retrievedOpenidConfiguration } from '../../www/front_src/src/Authentication/Openid/defaults';
import {
  labelEnableOpenIDConnectAuthentication,
  labelOpenIDConnectConfiguration
} from '../../www/front_src/src/Authentication/Openid/translatedLabels';
import { retrievedSAMLConfiguration } from '../../www/front_src/src/Authentication/SAML/defaults';
import { labelSAMLConfiguration } from '../../www/front_src/src/Authentication/SAML/translatedLabels';
import {
  labelMixed,
  labelRolesMapping
} from '../../www/front_src/src/Authentication/shared/translatedLabels';
import {
  labelActivation,
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../../www/front_src/src/Authentication/translatedLabels';
import { retrievedWebSSOConfiguration } from '../../www/front_src/src/Authentication/WebSSO/defaults';
import {
  labelBlacklistClientAddresses,
  labelDefineWebSSOConfiguration,
  labelEnableWebSSOAuthentication,
  labelInvalidIPAddress,
  labelInvalidRegex,
  labelLoginHeaderAttributeName,
  labelPatternMatchLogin,
  labelPatternReplaceLogin,
  labelTrustedClientAddresses,
  labelWebSSOConfiguration,
  labelWebSSOOnly
} from '../../www/front_src/src/Authentication/WebSSO/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Authentication/Authentication and Authentication/WebSSO/WebSSO.

const providerEndpoint = (provider: string): string =>
  `./api/latest/administration/authentication/providers/${provider}`;

test.use({ viewport: { height: 900, width: 1440 } });

test.describe('Authentication configuration', () => {
  const storyId = 'Authentication/Authentication/Default';
  const subTabs = [
    labelGroupsMapping,
    labelRolesMapping,
    labelAutoImportUsers,
    labelAuthenticationConditions,
    labelIdentityProvider
  ];

  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: providerEndpoint('local'),
      response: defaultPasswordSecurityPolicyAPI
    });
  });

  test('loads the local provider by default', async ({ mount, page }) => {
    const local = page.waitForRequest(/providers\/local$/);

    await mount(storyId);

    await local;
  });

  test('opens the OpenID Connect configuration and its sub tabs', async ({
    mockApi,
    mount
  }) => {
    const openid = await mockApi({
      endpoint: providerEndpoint('openid'),
      response: retrievedOpenidConfiguration
    });
    const component = await mount(storyId);

    await component
      .getByRole('tab', { name: labelOpenIDConnectConfiguration })
      .click();

    await expect(
      component.getByLabel(labelEnableOpenIDConnectAuthentication)
    ).toBeAttached();
    expect(openid.urls).not.toHaveLength(0);
    for (const tab of subTabs) {
      await component.getByTestId(`tab-${tab}`).click();
    }
  });

  test('opens the Web SSO configuration', async ({ mockApi, mount, page }) => {
    await mockApi({
      endpoint: providerEndpoint('web-sso'),
      response: retrievedWebSSOConfiguration
    });
    const component = await mount(storyId);
    const webSSO = page.waitForRequest(/providers\/web-sso$/);

    await component.getByText(labelWebSSOConfiguration).click();

    await webSSO;
  });

  test('opens the SAML configuration and its sub tabs', async ({
    mockApi,
    mount
  }) => {
    const saml = await mockApi({
      endpoint: providerEndpoint('saml'),
      response: retrievedSAMLConfiguration
    });
    const component = await mount(storyId);

    await component.getByText(labelSAMLConfiguration).click();

    await expect.poll(() => saml.urls.length).toBeGreaterThan(0);
    for (const tab of subTabs) {
      await component.getByTestId(`tab-${tab}`).click();
    }
  });
});

test.describe('Web SSO configuration form', () => {
  const storyId = 'Authentication/Authentication/WebSSO';

  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: providerEndpoint('web-sso'),
      response: retrievedWebSSOConfiguration
    });
  });

  test('displays the saved configuration', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);

    await expect(
      component.getByText(labelDefineWebSSOConfiguration)
    ).toBeVisible();
    await expect(
      component.getByRole('heading', { name: labelActivation })
    ).toBeVisible();
    await expect(
      component.getByLabel(labelEnableWebSSOAuthentication)
    ).toBeChecked();
    await expect(component.getByLabel(labelWebSSOOnly)).not.toBeChecked();
    await expect(component.getByLabel(labelMixed)).toBeChecked();
    await expect(
      component.getByLabel(labelTrustedClientAddresses)
    ).toBeVisible();
    await expect(
      component.getByLabel(labelBlacklistClientAddresses)
    ).toBeVisible();
    await expect(component.getByText('127.0.0.1', { exact: true })).toHaveCount(
      2
    );
    for (const label of [
      labelLoginHeaderAttributeName,
      labelPatternMatchLogin,
      labelPatternReplaceLogin
    ]) {
      await expect(component.getByLabel(label)).toHaveValue('');
    }

    await expectThemedScreenshot(component, 'web-sso-configuration');
  });

  test('saves a modified field', async ({ mockApi, mount }) => {
    const save = await mockApi({
      endpoint: providerEndpoint('web-sso'),
      method: 'PUT'
    });
    const component = await mount(storyId);

    await component.getByLabel(labelLoginHeaderAttributeName).fill('admin');
    await component.getByRole('heading', { name: labelActivation }).click();
    await component.getByLabel('save button').click();

    await expect
      .poll(() => save.bodies)
      .toEqual([
        { ...retrievedWebSSOConfiguration, login_header_attribute: 'admin' }
      ]);
  });

  test('rejects invalid patterns and addresses', async ({ mount, page }) => {
    const component = await mount(storyId);
    const trusted = component.getByLabel(labelTrustedClientAddresses);
    const blacklist = component.getByLabel(labelBlacklistClientAddresses);

    await component.getByLabel(labelPatternMatchLogin).fill('invalid-pattern^');
    await component.getByLabel(labelPatternReplaceLogin).click();
    await expect(component.getByText(labelInvalidRegex)).toBeVisible();

    await component
      .getByLabel(labelPatternReplaceLogin)
      .fill('$invalid-pattern');
    await trusted.click();
    await expect(component.getByText(labelInvalidRegex)).toHaveCount(2);

    await trusted.fill('invalid domain');
    await trusted.press('Enter');
    await blacklist.click();
    await expect(
      page.getByText(`invalid domain: ${labelInvalidIPAddress}`)
    ).toBeVisible();

    await blacklist.fill('127.0.0.1111');
    await blacklist.press('Enter');
    await trusted.click();
    await expect(
      page.getByText(`127.0.0.1111: ${labelInvalidIPAddress}`)
    ).toBeVisible();

    await expect(component.getByTestId(labelSave)).toBeDisabled();
    await expect(component.getByText(labelReset)).toBeEnabled();
  });

  test('resets a modified field after confirmation', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId);
    const loginHeader = component.getByLabel(labelLoginHeaderAttributeName);

    await loginHeader.fill('admin');
    await expect(loginHeader).toHaveValue('admin');

    await component.getByText(labelReset).click();
    const dialog = page.getByRole('dialog');
    await expect(
      dialog.getByRole('heading', { name: labelResetTheForm })
    ).toBeVisible();
    await expect(dialog.getByText(labelDoYouWantToResetTheForm)).toBeVisible();
    await dialog.getByLabel(labelReset).click();

    await expect(loginHeader).toHaveValue('');
  });
});
