import type { Locator, Page } from '@playwright/test';

import {
  labelDoYouWantToResetTheForm,
  labelReset,
  labelResetTheForm,
  labelSave
} from '../../www/front_src/src/Authentication/Local/translatedLabels';
import {
  labelAuthorizationEndpoint,
  labelBaseUrl,
  labelBlacklistClientAddresses,
  labelClientID,
  labelClientSecret,
  labelDefineOpenIDConnectConfiguration,
  labelDefineYourEndpoint,
  labelDisableVerifyPeer,
  labelEmailAttributePath,
  labelEnableOpenIDConnectAuthentication,
  labelEndSessionEndpoint,
  labelFullnameAttributePath,
  labelGroupValue,
  labelIntrospectionEndpoint,
  labelIntrospectionTokenEndpoint,
  labelInvalidIPAddress,
  labelInvalidURL,
  labelLoginAttributePath,
  labelOpenIDConnectOnly,
  labelOther,
  labelRedirectUrl,
  labelScopes,
  labelTokenEndpoint,
  labelTrustedClientAddresses,
  labelUseBasicAuthenticatonForTokenEndpointAuthentication,
  labelUserInformationEndpoint
} from '../../www/front_src/src/Authentication/Openid/translatedLabels';
import {
  labelAclAccessGroup,
  labelApplyOnlyFirtsRole,
  labelConditionsAttributePath,
  labelConditionValue,
  labelContactGroup,
  labelContactTemplate,
  labelDeleteRelation,
  labelEnableAutoImport,
  labelEnableAutomaticManagement,
  labelEnableConditionsOnIdentityProvider,
  labelGroupsAttributePath,
  labelMixed,
  labelRolesAttributePath,
  labelRolesMapping
} from '../../www/front_src/src/Authentication/shared/translatedLabels';
import {
  labelActivation,
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../../www/front_src/src/Authentication/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Authentication/Openid/OpenId.
const storyId = 'Authentication/Authentication/OpenId';
const openidEndpoint =
  './api/latest/administration/authentication/providers/openid';

const openid = {
  authentication_conditions: {
    attribute_path: 'auth attribute path',
    authorized_values: ['authorized'],
    blacklist_client_addresses: ['127.0.0.1'],
    endpoint: { custom_endpoint: null, type: 'introspection_endpoint' },
    is_enabled: false,
    trusted_client_addresses: ['127.0.0.1']
  },
  authentication_type: 'client_secret_post',
  authorization_endpoint: '/authorize',
  auto_import: false,
  base_url: 'https://localhost:8080',
  client_id: 'client_id',
  client_secret: 'client_secret',
  connection_scopes: ['openid'],
  contact_template: null,
  email_bind_attribute: 'email',
  endsession_endpoint: '/logout',
  fullname_bind_attribute: 'lastname',
  groups_mapping: {
    attribute_path: 'group attribute path',
    endpoint: { custom_endpoint: '/group/endpoint', type: 'custom_endpoint' },
    is_enabled: true,
    relations: []
  },
  introspection_token_endpoint: '/introspect',
  is_active: true,
  is_forced: false,
  login_claim: 'sub',
  redirect_url: '',
  roles_mapping: {
    apply_only_first_role: true,
    attribute_path: 'role attribute path',
    endpoint: { custom_endpoint: '/role/endpoint', type: 'custom_endpoint' },
    is_enabled: false,
    relations: []
  },
  token_endpoint: '/token',
  userinfo_endpoint: '/userinfo',
  verify_peer: false
};

const entities = (label: string) => ({
  meta: { limit: 10, page: 1, total: 2 },
  result: [
    { id: 1, name: `${label} 1` },
    { id: 2, name: `${label} 2` }
  ]
});

const lookups = {
  accessGroups: './api/latest/configuration/access-groups',
  contactGroups: './api/latest/configuration/contacts/groups',
  contactTemplates: './api/latest/configuration/contacts/templates'
};

let requests: Record<keyof typeof lookups, { urls: Array<URL> }>;

test.use({ viewport: { height: 900, width: 1440 } });

test.beforeEach(async ({ mockApi }) => {
  await mockApi({ endpoint: openidEndpoint, response: openid });
  requests = {
    accessGroups: await mockApi({
      endpoint: lookups.accessGroups,
      response: entities('Access Group')
    }),
    contactGroups: await mockApi({
      endpoint: lookups.contactGroups,
      response: entities('Contact Group')
    }),
    contactTemplates: await mockApi({
      endpoint: lookups.contactTemplates,
      response: entities('Contact Template')
    })
  };
});

const all = (component: Locator, label: string): Locator =>
  component.getByLabel(label, { exact: true });

const mountUnfolded = async (
  mount: (id: string) => Promise<Locator>
): Promise<Locator> => {
  const component = await mount(storyId);
  await expect(
    component.getByLabel(labelEnableOpenIDConnectAuthentication)
  ).toBeChecked();
  for (const section of [
    labelGroupsMapping,
    labelRolesMapping,
    labelAutoImportUsers,
    labelAuthenticationConditions,
    labelIdentityProvider
  ]) {
    await component
      .locator(`[data-section-group-form-id="${section}"]`)
      .click();
  }

  return component;
};

const pick = async (page: Page, name: string): Promise<void> => {
  await page.getByRole('option', { name }).click();
};

test('displays the saved configuration', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mountUnfolded(mount);

  await expect(
    component.getByText(labelDefineOpenIDConnectConfiguration)
  ).toBeAttached();
  await expect(
    component.getByRole('heading', { name: labelActivation })
  ).toBeAttached();
  await expectThemedScreenshot(component, 'openid-configuration');

  await expect(component.getByLabel(labelOpenIDConnectOnly)).not.toBeChecked();
  await expect(component.getByLabel(labelMixed)).toBeChecked();
  await expect(
    component.getByLabel(labelTrustedClientAddresses)
  ).toBeAttached();
  await expect(
    component.getByLabel(labelBlacklistClientAddresses)
  ).toBeAttached();
  await expect(component.getByText('127.0.0.1', { exact: true })).toHaveCount(
    2
  );

  const values: Array<[string, string]> = [
    [labelBaseUrl, 'https://localhost:8080'],
    [labelAuthorizationEndpoint, '/authorize'],
    [labelTokenEndpoint, '/token'],
    [labelIntrospectionTokenEndpoint, '/introspect'],
    [labelEndSessionEndpoint, '/logout'],
    [labelLoginAttributePath, 'sub'],
    [labelClientID, 'client_id'],
    [labelClientSecret, 'client_secret'],
    [labelEmailAttributePath, 'email'],
    [labelFullnameAttributePath, 'lastname'],
    [labelConditionsAttributePath, 'auth attribute path'],
    [labelGroupsAttributePath, 'group attribute path'],
    [labelRedirectUrl, '']
  ];
  for (const [label, value] of values) {
    await expect(component.getByLabel(label, { exact: true })).toHaveValue(
      value
    );
  }
  await expect(
    component.getByLabel(labelUserInformationEndpoint)
  ).toBeAttached();
  await expect(component.getByLabel(labelScopes)).toBeAttached();
  await expect(component.getByText('openid', { exact: true })).toBeAttached();

  for (const label of [
    labelUseBasicAuthenticatonForTokenEndpointAuthentication,
    labelEnableAutoImport,
    labelDisableVerifyPeer,
    labelEnableConditionsOnIdentityProvider
  ]) {
    await expect(component.getByLabel(label)).not.toBeChecked();
  }
  await expect(
    all(component, labelIntrospectionEndpoint).first()
  ).toBeChecked();
  await expect(all(component, labelConditionValue).first()).toHaveValue(
    'authorized'
  );
  await expect(
    all(component, labelEnableAutomaticManagement).first()
  ).not.toBeChecked();
  await expect(all(component, labelApplyOnlyFirtsRole).first()).toBeChecked();
  await expect(all(component, labelRolesAttributePath).first()).toHaveValue(
    'role attribute path'
  );
  await expect(all(component, labelOther).nth(1)).toBeChecked();
  await expect(all(component, labelOther).nth(2)).toBeChecked();
  await expect(all(component, labelDefineYourEndpoint).nth(0)).toHaveValue(
    '/role/endpoint'
  );
  await expect(all(component, labelDefineYourEndpoint).nth(1)).toHaveValue(
    '/group/endpoint'
  );
});

test('rejects an invalid URL and invalid addresses', async ({
  mount,
  page
}) => {
  const component = await mountUnfolded(mount);

  await component.getByLabel(labelBaseUrl).fill('invalid base url');
  await component
    .getByLabel(labelTrustedClientAddresses)
    .fill('invalid domain');
  await component.getByLabel(labelTrustedClientAddresses).press('Enter');
  await component
    .getByLabel(labelBlacklistClientAddresses)
    .fill('127.0.0.1111');
  await component.getByLabel(labelBlacklistClientAddresses).press('Enter');
  await component.getByLabel(labelBaseUrl).click();

  await expect(component.getByText(labelInvalidURL)).toBeAttached();
  await expect(
    page.getByText(`invalid domain: ${labelInvalidIPAddress}`)
  ).toBeAttached();
  await expect(
    page.getByText(`127.0.0.1111: ${labelInvalidIPAddress}`)
  ).toBeAttached();
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeDisabled();
  await expect(component.getByText(labelReset)).toBeEnabled();
});

test('saves the modified configuration', async ({ mockApi, mount, page }) => {
  const save = await mockApi({ endpoint: openidEndpoint, method: 'PUT' });
  const component = await mountUnfolded(mount);

  await component.getByLabel(labelBaseUrl).fill('http://localhost:8081/login');
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeEnabled();
  await component.getByLabel(labelContactGroup).click();
  await pick(page, 'Contact Group 2');
  await all(component, labelGroupValue).first().fill('groupValue');
  await component.getByRole('button', { name: labelSave }).click();

  await expect
    .poll(() => save.bodies)
    .toEqual([
      {
        ...openid,
        base_url: 'http://localhost:8081/login',
        groups_mapping: {
          ...openid.groups_mapping,
          relations: [{ contact_group_id: 2, group_value: 'groupValue' }]
        },
        redirect_url: null
      }
    ]);
});

test('resets the modified configuration after confirmation', async ({
  mount,
  page
}) => {
  const component = await mountUnfolded(mount);

  await component.getByLabel(labelBaseUrl).fill('http://localhost:8081/login');
  await component.getByText(labelReset).click();
  const dialog = page.getByRole('dialog');
  await expect(
    dialog.getByRole('heading', { name: labelResetTheForm })
  ).toBeVisible();
  await expect(dialog.getByText(labelDoYouWantToResetTheForm)).toBeVisible();
  await dialog.getByLabel(labelReset).click();

  await expect(component.getByLabel(labelBaseUrl)).toHaveValue(
    'https://localhost:8080'
  );
});

test('ignores a cleared auto import field once auto import is off', async ({
  mount
}) => {
  const component = await mountUnfolded(mount);
  const save = component.getByRole('button', { name: labelSave });

  await component.getByLabel(labelEnableAutoImport).click();
  await component.getByLabel(labelEmailAttributePath).clear();
  await expect(save).toBeDisabled();

  await component.getByLabel(labelEnableAutoImport).click();
  await expect(save).toBeEnabled();
});

const lookupFields = [
  {
    field: labelContactTemplate,
    key: 'contactTemplates' as const,
    value: 'Contact Template 2'
  },
  {
    field: labelAclAccessGroup,
    key: 'accessGroups' as const,
    value: 'Access Group 2'
  },
  {
    field: labelContactGroup,
    key: 'contactGroups' as const,
    value: 'Contact Group 2'
  }
];

for (const { field, key, value } of lookupFields) {
  test(`picks a ${field} from the retrieved options`, async ({
    mount,
    page
  }) => {
    const component = await mountUnfolded(mount);

    await component.getByLabel(labelEnableAutoImport).click();
    await all(component, field).first().click();
    await pick(page, value);

    await expect(all(component, field).first()).toHaveValue(value);
    const search = requests[key].urls.at(-1)?.search;
    expect(search).toBe(
      `?page=1&sort_by=${encodeURIComponent('{"name":"ASC"}')}&search=${encodeURIComponent('{"$and":[]}')}`
    );
  });
}

test('requires the groups mapping custom endpoint', async ({ mount }) => {
  const component = await mountUnfolded(mount);
  const save = component.getByRole('button', { name: labelSave });

  await component.getByLabel(labelBaseUrl).fill('http://localhost:8081/login');
  await expect(save).toBeEnabled();
  await all(component, labelDefineYourEndpoint).last().clear();

  await expect(save).toBeDisabled();
});

test('adds a contact group relation once the first one is valid', async ({
  mount,
  page
}) => {
  const component = await mountUnfolded(mount);

  await component.getByLabel(labelContactGroup).click();
  await pick(page, 'Contact Group 2');
  await component.getByLabel(labelGroupValue).fill('groupValue');

  await expect(all(component, labelContactGroup).first()).toHaveValue(
    'Contact Group 2'
  );
  await expect(all(component, labelGroupValue)).toHaveCount(2);
  await expect(all(component, labelContactGroup)).toHaveCount(2);
});

test('shows a custom endpoint field for the "Other" option', async ({
  mount
}) => {
  const component = await mountUnfolded(mount);
  const endpoints = all(component, labelDefineYourEndpoint);

  await expect(endpoints).toHaveCount(2);
  await all(component, labelOther).first().click();

  await expect(endpoints).toHaveCount(3);
});

test('adds a condition value and its delete action when the last one is typed', async ({
  mount
}) => {
  const component = await mountUnfolded(mount);

  await expect(all(component, labelConditionValue)).toHaveCount(2);
  await expect(all(component, labelDeleteRelation)).toHaveCount(1);

  await all(component, labelConditionValue).nth(1).fill('some text');

  await expect(all(component, labelConditionValue)).toHaveCount(3);
  await expect(all(component, labelDeleteRelation)).toHaveCount(2);
});
