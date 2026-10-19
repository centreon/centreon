import type { Locator, Page } from '@playwright/test';

import {
  editedRuleFormData,
  editedRuleFormDataiWithAllBusinessViews,
  editedRuleFormDataiWithBusinessViews,
  editedRuleFormDataWithAllContactsAndContactGroups,
  findBusinessViewsResponse,
  findResourceAccessRuleResponse
} from '../../www/front_src/src/ResourceAccessManagement/AddEditResourceAccessRule/specs/testUtils';
import {
  labelAddFilter,
  labelAddNewDataset,
  labelAddResourceDatasets,
  labelAllBusinessViews,
  labelAllContactGroups,
  labelAllContacts,
  labelAllHostGroups,
  labelAllHostGroupsSelected,
  labelAllResourcesSelected,
  labelBusinessView,
  labelContactsAndContactGroups,
  labelDescription,
  labelDoYouWantToQuitWithoutSaving,
  labelEditResourceAccessRule,
  labelExit,
  labelName,
  labelNameAlreadyExists,
  labelRequired,
  labelResourceAccessRuleEditedSuccess,
  labelRuleProperies,
  labelSave,
  labelSelectResource,
  labelSelectResourceType,
  labelYourFormHasUnsavedChanges
} from '../../www/front_src/src/ResourceAccessManagement/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from ResourceAccessManagement/AddEditResourceAccessRule/specs/ModalEdit.
const storyId = 'AddEditResourceAccessRule/AddEditResourceAccessRuleModal/Edit';
const ruleEndpoint = './api/latest/administration/resource-access/rules/1';

let edition: { bodies: Array<unknown> };

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: ruleEndpoint,
    response: findResourceAccessRuleResponse()
  });
  edition = await mockApi({
    endpoint: ruleEndpoint,
    method: 'PUT',
    response: { status: 'ok' }
  });
  await mockApi({
    endpoint: './api/latest/bam/configuration/business-views',
    response: findBusinessViewsResponse
  });
});

// The modal is portalled outside the mounted root.
const openRule = async (
  mount: (storyId: string, props?: object) => Promise<Locator>,
  page: Page,
  props = {}
) => {
  await mount(storyId, props);
  const modal = page.getByRole('dialog');
  await expect(modal.getByLabel(labelName)).toHaveValue('rule1');

  return modal;
};

const option = (page: Page, name: string) =>
  page.getByRole('option', { exact: true, name });

// Keeps only the first dataset, without its host filter.
const removeSecondaryEntries = async (modal: Locator) => {
  await modal.getByTestId('DeleteOutlineIcon').last().click();
  await modal.getByTestId('Delete').last().click();
};

const pickLastResourceType = async (
  modal: Locator,
  page: Page,
  type: string
) => {
  await modal.getByLabel(labelSelectResourceType).last().click();
  await option(page, type).click();
};

const expectEdited = async (page: Page, body?: unknown) => {
  await expect.poll(() => edition.bodies.length).toBe(1);
  if (body) {
    expect(edition.bodies[0]).toEqual(body);
  }
  await expect(
    page.getByText(labelResourceAccessRuleEditedSuccess)
  ).toBeVisible();
};

test('displays the rule and the modal actions', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const modal = await openRule(mount, page);

  await expect(modal.getByText(labelEditResourceAccessRule)).toBeVisible();
  await expect(modal.getByText(labelRuleProperies)).toBeVisible();
  await expect(modal.getByText(labelAddResourceDatasets)).toBeVisible();
  await expect(modal.getByText(labelContactsAndContactGroups)).toBeVisible();
  await expect(modal.getByLabel(labelExit)).toBeEnabled();
  await expect(modal.getByLabel(labelSave)).toBeDisabled();

  await expectThemedScreenshot(modal, 'resource-access-rule-edit');
});

test('displays the configured resources, contacts and contact groups', async ({
  mount,
  page
}) => {
  const modal = await openRule(mount, page);

  for (const text of [
    'Host group',
    'Linux-Servers',
    'Host',
    'Centreon-Server',
    'Service',
    'Disk-/var',
    'Disk-/usr',
    'Disk-/opt',
    'Disk-/',
    'admin admin',
    'centreon-gorgone',
    'Guest',
    'Supervisor'
  ]) {
    await expect(modal.getByText(text, { exact: true }).first()).toBeVisible();
  }
});

test.describe('name validation', () => {
  for (const [name, error] of [
    ['', labelRequired],
    ['Rule 2', labelNameAlreadyExists]
  ]) {
    test(`shows "${error}" and disables Save`, async ({ mount, page }) => {
      const modal = await openRule(mount, page);
      await modal.getByLabel(labelName).fill(name);
      await modal.getByText(labelRuleProperies).click();

      await expect(modal.getByText(error)).toBeVisible();
      await expect(modal.getByLabel(labelSave)).toBeDisabled();
    });
  }
});

test('enables Save once a value changes', async ({ mount, page }) => {
  const modal = await openRule(mount, page);
  await expect(modal.getByLabel(labelSave)).toBeDisabled();

  await modal.getByLabel(labelDescription).fill('Rule 1 description');
  await expect(modal.getByLabel(labelSave)).toBeEnabled();
});

test.describe('edition', () => {
  test('sends a changed dataset', async ({ mount, page }) => {
    const modal = await openRule(mount, page);
    await modal.getByTestId('DeleteOutlineIcon').last().click();
    await modal.getByLabel(labelSave).click();

    await expectEdited(page);
  });

  test('sends all resources', async ({ mount, page }) => {
    const modal = await openRule(mount, page);
    await removeSecondaryEntries(modal);
    await pickLastResourceType(modal, page, 'All resources');

    await expect(modal.getByLabel(labelAllResourcesSelected)).toBeVisible();
    await expect(modal.getByTestId(labelSelectResource)).toBeDisabled();
    await expect(modal.getByLabel(labelAddFilter)).toBeDisabled();
    await expect(modal.getByLabel(labelAddNewDataset)).toBeDisabled();
    await modal.getByLabel(labelSave).click();

    await expectEdited(page);
  });

  const selectAllHostGroups = async (modal: Locator) => {
    await removeSecondaryEntries(modal);
    await modal.getByLabel(labelName).fill('rule#1');
    await modal.getByLabel(labelAllHostGroups).click();
    const selected = modal.getByLabel(labelAllHostGroupsSelected);
    await expect(selected).toBeVisible();
    await expect(selected).toBeDisabled();
  };

  test('sends all host groups', async ({ mount, page }) => {
    const modal = await openRule(mount, page);
    await selectAllHostGroups(modal);
    await modal.getByLabel(labelSave).click();

    await expectEdited(page, editedRuleFormData);
  });

  test('sends all contacts and contact groups', async ({ mount, page }) => {
    const modal = await openRule(mount, page);
    await selectAllHostGroups(modal);
    await modal.getByLabel(labelAllContacts).click();
    await modal.getByLabel(labelAllContactGroups).click();
    await modal.getByLabel(labelSave).click();

    await expectEdited(page, editedRuleFormDataWithAllContactsAndContactGroups);
  });
});

test.describe('with BAM', () => {
  const switchToBusinessViews = async (modal: Locator, page: Page) => {
    await removeSecondaryEntries(modal);
    await modal.getByLabel(labelName).fill('rule#1');
    await pickLastResourceType(modal, page, labelBusinessView);
  };

  test('sends the selected business views', async ({ mount, page }) => {
    const modal = await openRule(mount, page, { isBamModuleInstalled: true });
    await switchToBusinessViews(modal, page);
    for (const view of ['BV1', 'BV2']) {
      await modal.getByTestId(labelSelectResource).last().click();
      await option(page, view).click();
    }
    await modal.getByLabel(labelSave).click();

    await expectEdited(page, editedRuleFormDataiWithBusinessViews);
  });

  test('sends all business views', async ({ mount, page }) => {
    const modal = await openRule(mount, page, { isBamModuleInstalled: true });
    await switchToBusinessViews(modal, page);
    await modal.getByText(labelAllBusinessViews).click();
    await modal.getByLabel(labelSave).click();

    await expectEdited(page, editedRuleFormDataiWithAllBusinessViews);
  });
});

test.describe('closing an edited form', () => {
  const closers: Array<[string, (page: Page) => Promise<void>]> = [
    ['the Exit button', (page) => page.getByLabel(labelExit).click()],
    ['the Close button', (page) => page.getByLabel('close').click()],
    ['a click outside', (page) => page.mouse.click(5, 5)]
  ];

  for (const [name, close] of closers) {
    test(`asks for a confirmation on ${name}`, async ({ mount, page }) => {
      const modal = await openRule(mount, page);
      await removeSecondaryEntries(modal);
      await close(page);

      await expect(
        page.getByText(labelYourFormHasUnsavedChanges)
      ).toBeVisible();
      await expect(
        page.getByText(labelDoYouWantToQuitWithoutSaving)
      ).toBeVisible();
    });
  }
});
