import type { Locator, Page } from '@playwright/test';

import { labelSave } from '../../www/front_src/src/Authentication/Local/translatedLabels';
import { retrievedSAMLConfiguration as saml } from '../../www/front_src/src/Authentication/SAML/defaults';
import {
  labelBothIdentityProviderAndCentreonUI,
  labelCentreonUIOnly,
  labelCertificate,
  labelDefineSAMLConfiguration,
  labelEmailAttribute,
  labelEnableSAMLAuthentication,
  labelEntityIdURL,
  labelFullNameAttribute,
  labelLogoutUrl,
  labelRemoteLoginUrl,
  labelRequestedAuthnContextComparison,
  labelRequired,
  labelSAMLOnly,
  labelUserIdAttribute
} from '../../www/front_src/src/Authentication/SAML/translatedLabels';
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
  labelGroupValue,
  labelMixed,
  labelRolesAttributePath,
  labelRolesMapping,
  labelRoleValue
} from '../../www/front_src/src/Authentication/shared/translatedLabels';
import {
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../../www/front_src/src/Authentication/translatedLabels';
import { expect, moveSortable, test } from './fixtures';

// Migrated from Authentication/SAML/SAMLConfiguration.
const storyId = 'Authentication/Authentication/SAML';
const samlEndpoint =
  './api/latest/administration/authentication/providers/saml';

const entities = (label: string) => ({
  meta: { limit: 2, page: 1, total: 2 },
  result: [
    { id: 1, name: `${label} 1` },
    { id: 2, name: `${label} 2` }
  ]
});

test.use({ viewport: { height: 900, width: 1440 } });

test.beforeEach(async ({ mockApi }) => {
  await mockApi({ endpoint: samlEndpoint, response: saml });
  await mockApi({
    endpoint: './api/latest/configuration/access-groups',
    response: entities('Access Group')
  });
  await mockApi({
    endpoint: './api/latest/configuration/contacts/groups',
    response: entities('Contact Group')
  });
});

const openSection = async (component: Locator, section: string) => {
  await component.locator(`[data-section-group-form-id="${section}"]`).click();
};
const all = (component: Locator, label: string): Locator =>
  component.getByLabel(label, { exact: true });

const loaded = async (component: Locator): Promise<void> => {
  await expect(
    component.getByLabel(labelEnableSAMLAuthentication)
  ).toBeChecked();
};

test('displays the saved configuration', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await expect(component.getByText(labelDefineSAMLConfiguration)).toBeVisible();
  await expect(component.getByLabel(labelSAMLOnly)).not.toBeChecked();
  await expect(component.getByLabel(labelMixed)).toBeChecked();
  await expectThemedScreenshot(component, 'saml-configuration');

  await component.getByText(labelIdentityProvider).first().click();
  await component.getByTestId('Identity provider-header').click();
  await expect(component.getByLabel(labelRemoteLoginUrl)).toHaveValue(
    saml.remote_login_url
  );
  await expect(component.getByLabel(labelEntityIdURL)).toHaveValue(
    saml.entity_id_url
  );
  await expect(component.getByLabel(labelCertificate)).toHaveValue(
    saml.certificate
  );
  await expect(component.getByLabel(labelUserIdAttribute)).toHaveValue(
    saml.user_id_attribute
  );
  // The comparison rule shows once the requested authentication context is on.
  await component.getByTestId('saml_requestAuthnContext').click();
  await expect(
    component.getByTestId(labelRequestedAuthnContextComparison)
  ).toHaveValue('exact');
  await expect(component.getByLabel(labelCentreonUIOnly)).not.toBeChecked();
  await expect(
    component.getByLabel(labelBothIdentityProviderAndCentreonUI)
  ).toBeChecked();
  await expect(component.getByLabel(labelLogoutUrl)).toHaveValue(
    saml.logout_from_url
  );

  await expect(
    component.getByLabel(labelEnableConditionsOnIdentityProvider)
  ).toBeChecked();
  await expect(component.getByLabel(labelConditionsAttributePath)).toHaveValue(
    saml.authentication_conditions.attribute_path
  );
  await expect(all(component, labelConditionValue)).toHaveCount(2);
  await expect(all(component, labelConditionValue).first()).toHaveValue(
    saml.authentication_conditions.authorized_values[0] as string
  );

  await expect(component.getByLabel(labelEnableAutoImport)).toBeChecked();
  await expect(component.getByLabel(labelContactTemplate)).toHaveValue(
    saml.contact_template.name
  );
  await expect(component.getByLabel(labelEmailAttribute)).toHaveValue(
    saml.email_bind_attribute
  );
  await expect(component.getByLabel(labelFullNameAttribute)).toHaveValue(
    saml.fullname_bind_attribute
  );

  const automaticManagement = all(component, labelEnableAutomaticManagement);
  await expect(automaticManagement.nth(0)).toBeChecked();
  await expect(component.getByLabel(labelApplyOnlyFirtsRole)).toBeChecked();
  await expect(component.getByLabel(labelRolesAttributePath)).toHaveValue(
    saml.roles_mapping.attribute_path
  );
  await expect(all(component, labelRoleValue)).toHaveCount(2);
  await expect(all(component, labelRoleValue).first()).toHaveValue(
    saml.roles_mapping.relations[0]?.claim_value as string
  );
  await expect(all(component, labelAclAccessGroup).first()).toHaveValue(
    saml.roles_mapping.relations[0]?.access_group.name as string
  );

  await expect(automaticManagement.nth(1)).toBeChecked();
  await expect(component.getByLabel(labelGroupsAttributePath)).toHaveValue(
    saml.groups_mapping.attribute_path
  );
  await expect(all(component, labelGroupValue)).toHaveCount(2);
  await expect(all(component, labelGroupValue).first()).toHaveValue(
    saml.groups_mapping.relations[0]?.group_value as string
  );
  await expect(all(component, labelContactGroup).first()).toHaveValue(
    saml.groups_mapping.relations[0]?.contact_group.name as string
  );
});

test('disables the auto import fields with the auto import', async ({
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelAutoImportUsers);
  await component.getByLabel(labelEnableAutoImport).click();

  for (const label of [
    labelContactTemplate,
    labelEmailAttribute,
    labelFullNameAttribute
  ]) {
    await expect(component.getByLabel(label)).toBeDisabled();
  }
});

test('hides the logout URL for a Centreon UI only logout', async ({
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelIdentityProvider);
  // A click while the section expands can be lost on a slow runner (CI):
  // retry until the choice is taken.
  await expect(async () => {
    await component.getByLabel(labelCentreonUIOnly).check({ timeout: 2_000 });
  }).toPass();

  await expect(component.getByLabel(labelLogoutUrl)).toHaveCount(0);
});

test('adds and removes authentication condition values', async ({ mount }) => {
  const component = await mount(storyId);
  await loaded(component);
  const values = all(component, labelConditionValue);

  await openSection(component, labelAuthenticationConditions);
  await values.nth(1).fill('value2');
  await expect(values).toHaveCount(3);
  await expect(values.nth(2)).toHaveValue('');

  await all(component, labelDeleteRelation).first().click();
  await expect(values).toHaveCount(2);
});

const sortHandle = (component: Locator): Locator =>
  component.getByTestId('UnfoldMoreIcon');

const pickOption = async (page: Page, name: string): Promise<void> => {
  await page.getByRole('option', { name }).click();
};

test('sorts the roles relations with the keyboard', async ({ mount, page }) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelRolesMapping);
  await all(component, labelRoleValue).nth(1).fill('A role');
  await all(component, labelAclAccessGroup).nth(1).click();
  await pickOption(page, 'Access Group 1');

  // The first move only reaches the row's own slot.
  await moveSortable(
    page,
    sortHandle(component).nth(1).locator('..'),
    'ArrowUp',
    2
  );

  await expect(all(component, labelRoleValue).first()).toHaveValue('A role');
  await expect(all(component, labelAclAccessGroup).first()).toHaveValue(
    'Access Group 1'
  );
});

test('removes a roles relation', async ({ mount }) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelRolesMapping);
  await expect(all(component, labelRoleValue).first()).toHaveValue('scope');
  // Hidden rows carry the label too, so an index depends on render timing:
  // the only visible button is the one of the "scope" relation. A click while
  // the section expands can be lost on a slow runner (CI): retry until done.
  await expect(async () => {
    await all(component, labelDeleteRelation)
      .locator('visible=true')
      .click({ timeout: 2_000 });
    await expect(all(component, labelRoleValue)).toHaveCount(1, {
      timeout: 2_000
    });
  }).toPass();
  await expect(all(component, labelRoleValue).first()).toHaveValue('');
});

test('drops the sort handles without "apply only first role"', async ({
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelRolesMapping);
  await component.getByLabel(labelApplyOnlyFirtsRole).click();

  await expect(sortHandle(component)).toHaveCount(0);
});

test('adds then removes groups relations', async ({ mount, page }) => {
  const component = await mount(storyId);
  await loaded(component);
  const groups = all(component, labelGroupValue);

  await openSection(component, labelGroupsMapping);
  await groups.nth(1).fill('A group');
  await all(component, labelContactGroup).nth(1).click();
  await pickOption(page, 'Contact Group 1');

  await expect(groups).toHaveCount(3);
  await expect(groups.nth(2)).toHaveValue('');
  await expect(all(component, labelContactGroup)).toHaveCount(3);

  await all(component, labelDeleteRelation).nth(2).click();
  await expect(groups).toHaveCount(2);
});

test('saves an updated field', async ({ mockApi, mount }) => {
  const save = await mockApi({ endpoint: samlEndpoint, method: 'PUT' });
  const component = await mount(storyId);
  await loaded(component);
  const saveButton = component.getByRole('button', { name: labelSave });

  await openSection(component, labelIdentityProvider);
  await expect(saveButton).toBeDisabled();
  await component.getByLabel(labelRemoteLoginUrl).fill('https://example.com');
  await saveButton.click();

  await expect.poll(() => save.bodies.length).toBe(1);
  expect(save.bodies[0]).toMatchObject({
    remote_login_url: 'https://example.com'
  });
});

test('requires the identity provider fields', async ({ mount }) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelIdentityProvider);
  for (const label of [
    labelRemoteLoginUrl,
    labelEntityIdURL,
    labelCertificate,
    labelUserIdAttribute
  ]) {
    await component.getByLabel(label).clear();
  }
  await component.getByLabel(labelRemoteLoginUrl).click();

  await expect(component.getByText(labelRequired)).toHaveCount(4);
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeDisabled();
});

test('requires the logout URL for an identity provider logout', async ({
  mount
}) => {
  const component = await mount(storyId);
  await loaded(component);

  await openSection(component, labelIdentityProvider);
  await component.getByLabel(labelLogoutUrl).clear();
  await component.getByLabel(labelBothIdentityProviderAndCentreonUI).click();

  await expect(component.getByText(labelRequired)).toBeVisible();
  await expect(
    component.getByRole('button', { name: labelSave })
  ).toBeDisabled();
});
