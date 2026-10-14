import type { Locator, Page } from '@playwright/test';

import {
  allResourcesFormData,
  findBusinessViewsResponse,
  findContactGroupsResponse,
  findContactsResponse,
  findHostCategoriesResponse,
  findHostGroupsResponse,
  findHostsResponse,
  findMetaServicesResponse,
  findServiceCategoriesResponse,
  findServiceGroupsResponse,
  findServicesResponse,
  formData,
  formDataWithAllBusinessViews,
  formDataWithAllContactGroups,
  formDataWithAllContacts,
  formDataWithAllHostGroups,
  formDataWithBusinessViews
} from '../../www/front_src/src/ResourceAccessManagement/AddEditResourceAccessRule/specs/testUtils';
import {
  labelActiveOrInactive,
  labelAddFilter,
  labelAddNewDataset,
  labelAllBusinessViews,
  labelAllBusinessViewsSelected,
  labelAllContactGroups,
  labelAllContacts,
  labelAllHostGroups,
  labelAllHostGroupsSelected,
  labelAllResourcesSelected,
  labelBusinessView,
  labelContactGroups,
  labelContacts,
  labelDescription,
  labelDoYouWantToQuitWithoutSaving,
  labelExit,
  labelName,
  labelResourceAccessRuleAddedSuccess,
  labelSave,
  labelSelectResource,
  labelSelectResourceType,
  labelYourFormHasUnsavedChanges
} from '../../www/front_src/src/ResourceAccessManagement/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from ResourceAccessManagement/AddEditResourceAccessRule/specs/ModalCreate.
const storyId =
  'AddEditResourceAccessRule/AddEditResourceAccessRuleModal/Create';
const configuration = './api/latest/configuration';

let creation: { bodies: Array<unknown> };

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ mockApi }) => {
  creation = await mockApi({
    endpoint: './api/latest/administration/resource-access/rules',
    method: 'POST',
    response: { status: 'ok' }
  });
  const lookups: Array<[string, unknown]> = [
    ['hosts/groups', findHostGroupsResponse],
    ['hosts/categories', findHostCategoriesResponse],
    ['hosts', findHostsResponse],
    ['services/groups', findServiceGroupsResponse],
    ['services/categories', findServiceCategoriesResponse],
    ['services', findServicesResponse],
    ['metaservices', findMetaServicesResponse],
    ['users', findContactsResponse],
    ['contacts/groups', findContactGroupsResponse]
  ];
  for (const [path, response] of lookups) {
    await mockApi({ endpoint: `${configuration}/${path}`, response });
  }
  await mockApi({
    endpoint: './api/latest/bam/configuration/business-views',
    response: findBusinessViewsResponse
  });
});

// The modal is portalled outside the mounted root.
const modal = (page: Page) => page.getByRole('dialog');

const option = (page: Page, name: string) =>
  page.getByRole('option', { exact: true, name });

const pickResourceType = async (
  component: Locator,
  page: Page,
  type: string
) => {
  await component.getByLabel(labelSelectResourceType).last().click();
  await option(page, type).click();
};

const pickResource = async (
  component: Locator,
  page: Page,
  resource: string
) => {
  await component.getByTestId(labelSelectResource).last().click();
  await option(page, resource).click();
};

const pickContacts = async (component: Locator, page: Page) => {
  for (const [field, name] of [
    [labelContacts, 'centreon-gorgone'],
    [labelContactGroups, 'Supervisors']
  ]) {
    await component.getByTestId(field).click();
    await option(page, name).click();
    await component.getByTestId(field).click();
  }
};

const fillName = async (component: Locator, name = 'rule#1') => {
  await component.getByLabel(labelName).fill(name);
  await component.getByLabel(labelDescription).fill(`${name}: Lorem ipsum...`);
};

const fillFormRequiredFields = async (component: Locator, page: Page) => {
  await fillName(component);
  await pickResourceType(component, page, 'Host group');
  await pickResource(component, page, 'Linux-Servers');
  await component.getByLabel(labelAddFilter).click();
  await pickResourceType(component, page, 'Host');
  await pickResource(component, page, 'Centreon-Server');
  await component.getByLabel(labelAddNewDataset).click();
  await pickResourceType(component, page, 'Service category');
  await pickResource(component, page, 'Ping');
  await pickContacts(component, page);
};

const expectCreated = async (page: Page, body: unknown) => {
  await expect.poll(() => creation.bodies).toEqual([body]);
  await expect(
    page.getByText(labelResourceAccessRuleAddedSuccess)
  ).toBeVisible();
};

test('displays an empty form', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  await mount(storyId);
  const component = modal(page);

  await expect(component.getByLabel(labelSave)).toBeDisabled();
  const name = component.getByLabel(labelName);
  await expect(name).toHaveValue('');
  await expect(name).toHaveAttribute('required', '');
  const description = component.getByLabel(labelDescription);
  await expect(description).toHaveValue('');
  await expect(description).not.toHaveAttribute('required');
  await expect(component.getByLabel(labelActiveOrInactive)).toBeEnabled();
  await expect(
    // The select has no accessible name: reach it through its label.
    component.getByLabel(labelSelectResourceType)
  ).toHaveText(labelSelectResourceType);
  await expect(component.getByTestId(labelSelectResource)).toBeVisible();
  await expect(component.getByLabel(labelAddFilter)).toBeDisabled();
  await expect(component.getByLabel(labelAddNewDataset)).toBeDisabled();

  await expectThemedScreenshot(component, 'resource-access-rule-create');
});

test('enables Save once the required fields are filled', async ({
  mount,
  page
}) => {
  await mount(storyId);
  const component = modal(page);
  await expect(component.getByLabel(labelSave)).toBeDisabled();

  await fillFormRequiredFields(component, page);
  await expect(component.getByLabel(labelSave)).toBeEnabled();
});

test.describe('dataset buttons', () => {
  const buttons = (component: Locator) => ({
    addDataset: component.getByLabel(labelAddNewDataset),
    addFilter: component.getByLabel(labelAddFilter)
  });

  test('stay disabled until a resource is selected', async ({
    mount,
    page
  }) => {
    await mount(storyId);
    const component = modal(page);
    const { addDataset, addFilter } = buttons(component);
    await expect(addFilter).toBeDisabled();
    await expect(addDataset).toBeDisabled();

    await pickResourceType(component, page, 'Host');
    await expect(addFilter).toBeDisabled();
    await expect(addDataset).toBeDisabled();

    await pickResource(component, page, 'Centreon-Server');
    await expect(addFilter).toBeEnabled();
    await expect(addDataset).toBeEnabled();

    // A new, empty filter disables them again.
    await addFilter.click();
    await expect(addFilter).toBeDisabled();
    await expect(addDataset).toBeDisabled();
  });

  for (const [type, resource] of [
    ['Metaservice', 'META_SERVICE_MEMORY_PARIS'],
    ['Service', 'Ping']
  ]) {
    test(`only allow a new dataset after a ${type.toLowerCase()}`, async ({
      mount,
      page
    }) => {
      await mount(storyId);
      const component = modal(page);
      const { addDataset, addFilter } = buttons(component);

      await pickResourceType(component, page, type);
      await pickResource(component, page, resource);
      await expect(addFilter).toBeDisabled();
      await expect(addDataset).toBeEnabled();
    });
  }
});

test.describe('deletion icons', () => {
  for (const testId of ['DeleteOutlineIcon', 'Delete']) {
    test(`removes the ${testId} icon once only one entry is left`, async ({
      mount,
      page
    }) => {
      await mount(storyId);
      const component = modal(page);
      await fillFormRequiredFields(component, page);

      const icons = component.getByTestId(testId);
      await expect(icons.first()).toBeVisible();
      await icons.first().click();
      await expect(icons.locator('visible=true')).toHaveCount(0);
    });
  }
});

test.describe('creation', () => {
  test('sends the form values', async ({ mount, page }) => {
    await mount(storyId);
    const component = modal(page);
    await fillFormRequiredFields(component, page);
    await component.getByLabel(labelSave).click();

    await expectCreated(page, formData);
  });

  test('sends all resources', async ({ mount, page }) => {
    await mount(storyId);
    const component = modal(page);
    await fillName(component, 'rule#0');
    await pickResourceType(component, page, 'All resources');

    await expect(component.getByLabel(labelAllResourcesSelected)).toBeVisible();
    await expect(component.getByTestId(labelSelectResource)).toBeDisabled();
    await expect(component.getByLabel(labelAddFilter)).toBeDisabled();
    await expect(component.getByLabel(labelAddNewDataset)).toBeDisabled();

    await pickContacts(component, page);
    await component.getByLabel(labelSave).click();
    await expectCreated(page, allResourcesFormData);
  });

  const selectAllHostGroups = async (component: Locator, page: Page) => {
    await fillName(component);
    await pickResourceType(component, page, 'Host group');
    await pickResource(component, page, 'Linux-Servers');
    await component.getByLabel(labelAllHostGroups).click();
    const selected = component.getByLabel(labelAllHostGroupsSelected);
    await expect(selected).toBeVisible();
    await expect(selected).toBeDisabled();
  };

  test('sends all host groups', async ({ mount, page }) => {
    await mount(storyId);
    const component = modal(page);
    await selectAllHostGroups(component, page);
    await pickContacts(component, page);
    await component.getByLabel(labelSave).click();

    await expectCreated(page, formDataWithAllHostGroups);
  });

  for (const [label, body] of [
    [labelAllContacts, formDataWithAllContacts],
    [labelAllContactGroups, formDataWithAllContactGroups]
  ] as const) {
    test(`sends ${label.toLowerCase()}`, async ({ mount, page }) => {
      await mount(storyId);
      const component = modal(page);
      await selectAllHostGroups(component, page);
      await component.getByLabel(label).click();
      await component.getByLabel(labelSave).click();

      await expectCreated(page, body);
    });
  }
});

test.describe('with BAM', () => {
  test('offers the business view resource type', async ({ mount, page }) => {
    await mount(storyId, { isBamModuleInstalled: true });
    const component = modal(page);
    await pickResourceType(component, page, labelBusinessView);

    await expect(component.getByText(labelAllBusinessViews)).toBeVisible();
    await expect(component.getByLabel(labelAddFilter)).toBeDisabled();
  });

  test('sends the selected business views', async ({ mount, page }) => {
    await mount(storyId, { isBamModuleInstalled: true });
    const component = modal(page);
    await fillName(component);
    await pickResourceType(component, page, labelBusinessView);
    await pickResource(component, page, 'BV1');
    await pickResource(component, page, 'BV2');
    await pickContacts(component, page);
    await component.getByLabel(labelSave).click();

    await expectCreated(page, formDataWithBusinessViews);
  });

  test('sends all business views', async ({ mount, page }) => {
    await mount(storyId, { isBamModuleInstalled: true });
    const component = modal(page);
    await fillName(component);
    await pickResourceType(component, page, labelBusinessView);
    await component.getByText(labelAllBusinessViews).click();
    const selected = component.getByLabel(labelAllBusinessViewsSelected);
    await expect(selected).toBeVisible();
    await expect(selected).toBeDisabled();
    await pickContacts(component, page);
    await component.getByLabel(labelSave).click();

    await expectCreated(page, formDataWithAllBusinessViews);
  });
});

test.describe('closing a filled form', () => {
  const closers: Array<[string, (page: Page) => Promise<void>]> = [
    ['the Exit button', (page) => page.getByLabel(labelExit).click()],
    ['the Close button', (page) => page.getByLabel('close').click()],
    ['a click outside', (page) => page.mouse.click(5, 5)]
  ];

  for (const [name, close] of closers) {
    test(`asks for a confirmation on ${name}`, async ({ mount, page }) => {
      await mount(storyId);
      const component = modal(page);
      await fillFormRequiredFields(component, page);
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
