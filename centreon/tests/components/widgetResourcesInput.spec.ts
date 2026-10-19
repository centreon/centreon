import type { Page } from '@playwright/test';

import {
  labelActivateRegex,
  labelAddFilter,
  labelDeactivateRegex,
  labelDelete,
  labelDoYouWantToLeaveTheClassicMode,
  labelDoYouWantToLeaveTheRegexMode,
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelLeave,
  labelMetaService,
  labelResourceType,
  labelSelectAResource,
  labelService,
  labelServiceCategory,
  labelServiceGroup,
  labelStay,
  labelYourChangesWillNotBeSavedIfYouSwitchClassicMode,
  labelYourChangesWillNotBeSavedIfYouSwitchRegexMode
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import widgetDataProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-data/properties.json';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/SingleInstancePage/Dashboard/AddEditWidget/
// WidgetProperties/Inputs/Resources/Resources.
const storyId = 'Inputs/Resources/Resources/Default';

interface Options {
  canEdit?: boolean;
  emptyData?: boolean;
  inputProps?: Record<string, unknown>;
  isEditing?: boolean;
  singleResourceSelection?: boolean;
}

const props = ({
  canEdit,
  emptyData = false,
  inputProps = {},
  isEditing,
  singleResourceSelection = false
}: Options = {}) => ({
  canEdit,
  extraValues: { data: emptyData ? {} : { resources: [] } },
  inputProps,
  isEditing,
  options: { property: 'value' },
  widgetProperties: { ...widgetDataProperties, singleResourceSelection }
});

const generateResources = (label: string) => ({
  meta: { limit: 10, page: 1, total: 10 },
  result: Array.from({ length: 10 }, (_, index) => ({
    id: index,
    name: `${label} ${index}`
  }))
});

// Resource types and the types a next filter line can use after them.
const resourceTypes = {
  [labelHost]: [labelServiceGroup, labelServiceCategory, labelService],
  [labelHostCategory]: [
    labelHostGroup,
    labelHost,
    labelServiceGroup,
    labelServiceCategory,
    labelService
  ],
  [labelHostGroup]: [
    labelHostCategory,
    labelHost,
    labelServiceGroup,
    labelServiceCategory,
    labelService
  ],
  [labelService]: [],
  [labelServiceCategory]: [labelServiceGroup, labelService],
  [labelServiceGroup]: [labelServiceCategory, labelService],
  [labelMetaService]: []
};

interface Mocks {
  hosts: MockedEndpoint;
  services: MockedEndpoint;
}

const mockResources = async (mockApi: MockApi): Promise<Mocks> => {
  const resources = await mockApi({
    endpoint: './api/latest/monitoring/resources',
    response: (url: URL) =>
      generateResources(
        url.searchParams.get('types') === '["metaservice"]'
          ? labelMetaService
          : labelHost
      )
  });
  for (const [endpoint, label] of [
    ['monitoring/hostgroups', labelHostGroup],
    ['monitoring/servicegroups', labelServiceGroup],
    ['monitoring/hosts/categories', labelHostCategory],
    ['monitoring/services/categories', labelServiceCategory]
  ]) {
    await mockApi({ endpoint, response: generateResources(label) });
  }

  return {
    hosts: resources,
    services: await mockApi({
      endpoint: 'monitoring/services/names',
      response: generateResources(labelService)
    })
  };
};

let mocks: Mocks;

test.beforeEach(async ({ mockApi }) => {
  mocks = await mockResources(mockApi);
});

const typeField = (page: Page, index = 0) =>
  page.getByTestId(labelResourceType).nth(index);
const resourceField = (page: Page, index = 0) =>
  page.getByTestId(labelSelectAResource).nth(index);
const addFilter = (page: Page) =>
  page.getByRole('button', { name: labelAddFilter });

const pickType = async (page: Page, type: string, index = 0) => {
  await typeField(page, index).locator('..').click();
  await page.getByRole('option', { exact: true, name: type }).click();
};
const pickResource = async (page: Page, name: string, index = 0) => {
  await resourceField(page, index).click();
  await page.getByRole('option', { exact: true, name }).click();
};

const optionNames = (page: Page) => page.getByRole('option').allInnerTexts();

test.describe('Resources', () => {
  test('displays a host then a service line when both are the default types', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(
      storyId,
      props({
        inputProps: {
          defaultResourceTypes: ['host', 'service'],
          forcedResourceType: 'service'
        },
        singleResourceSelection: true
      })
    );
    await expect(typeField(page, 0)).toHaveValue('host');
    await expect(typeField(page, 1)).toHaveValue('service');
    await expect(resourceField(page, 1)).toBeDisabled();
    await pickResource(page, 'Host 0');
    const servicesRequest = page.waitForRequest((request) =>
      request.url().includes('search=')
    );
    await resourceField(page, 1).click();
    expect((await servicesRequest).url()).toContain(
      'page=1&limit=30&search=%7B%22%24and%22%3A%5B%7B%22%24or%22%3A%5B%7B%22host.name%22%3A%7B%22%24in%22%3A%5B%22Host%200%22%5D%7D%7D%5D%7D%5D%7D'
    );
    await page.getByRole('option', { name: 'Service 0' }).click();
    await expect(resourceField(page, 0)).toHaveValue('Host 0');
    await expect(resourceField(page, 1)).toHaveValue('Service 0');
    await expectThemedScreenshot(page, 'widget-resources-host-service');
  });

  test('adds a filter line once the first line is filled', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await expect(addFilter(page)).toBeDisabled();
    await pickType(page, labelHost);
    await pickResource(page, 'Host 0');
    await addFilter(page).click();
    await expect(page.getByTestId(labelResourceType)).toHaveCount(2);
  });

  test('hides the Add filter button for a single resource type', async ({
    mount,
    page
  }) => {
    await mount(storyId, props({ inputProps: { singleResourceType: true } }));
    await expect(addFilter(page)).toHaveCount(0);
    await expect(page.getByLabel(labelDelete)).toBeHidden();
  });

  test('lists only the restricted resource types', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(
      storyId,
      props({
        inputProps: {
          restrictedResourceTypes: ['host-group', 'host', 'service-category']
        }
      })
    );
    await typeField(page).locator('..').click();
    await expect
      .poll(() => optionNames(page))
      .toEqual(
        [labelHost, labelHostGroup, labelServiceCategory].sort((a, b) =>
          optionOrder(a) > optionOrder(b) ? 1 : -1
        )
      );
    await expectThemedScreenshot(page, 'widget-resources-restricted-types');
  });

  test('deletes a resource when its chip icon is clicked', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await pickType(page, labelHost);
    await pickResource(page, 'Host 0');
    await page.getByTestId('CancelIcon').click();
    await resourceField(page).blur();
    await expect(page.getByText('Host 0')).toHaveCount(0);
  });

  test('selects a type and a resource when the form has no data yet', async ({
    mount,
    page
  }) => {
    await mount(storyId, props({ emptyData: true }));
    await addFilter(page).click();
    await pickType(page, labelHost);
    await pickResource(page, 'Host 0');
    await expect(typeField(page)).toHaveValue('host');
    await expect(page.getByTestId('tag-option-chip-0')).toHaveText('Host 0');
    await page.getByTestId('CancelIcon').click();
    await resourceField(page).blur();
    await expect(page.getByText('Host 0')).toHaveCount(0);
  });

  test('does not list the excluded resource types', async ({ mount, page }) => {
    await mount(
      storyId,
      props({ inputProps: { excludedResourceTypes: ['meta-service', 'host'] } })
    );
    await typeField(page).locator('..').click();
    await expect(page.getByRole('option').first()).toBeVisible();
    const names = await optionNames(page);
    expect(names).not.toContain(labelMetaService);
    expect(names).not.toContain(labelHost);
  });
});

test.describe('Resources disabled', () => {
  for (const [reason, options] of [
    ['the edition mode is not activated', { canEdit: true, isEditing: false }],
    ['the rights are not sufficient', { canEdit: false }]
  ] as const) {
    test(`disables the fields when ${reason}`, async ({ mount, page }) => {
      await mount(storyId, props(options));
      await expect(typeField(page)).toBeDisabled();
      await expect(resourceField(page)).toBeDisabled();
    });
  }
});

// The order of resourceTypes, which is the order of the menu.
const optionOrder = (name: string): number =>
  Object.keys(resourceTypes).indexOf(name);

test.describe('Resources tree', () => {
  test('lists every resource type on the first line', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, props());
    await typeField(page).locator('..').click();
    await expect
      .poll(() => optionNames(page))
      .toEqual(Object.keys(resourceTypes));
    await expectThemedScreenshot(page, 'widget-resources-all-types');
  });

  test('disables the Add filter button once a service is the type', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await pickType(page, labelService);
    await expect(addFilter(page)).toBeDisabled();
  });

  for (const [type, available] of Object.entries(resourceTypes)) {
    if (available.length === 0) {
      continue;
    }
    test(`lists only the types available after a ${type} line`, async ({
      mount,
      page
    }) => {
      await mount(storyId, props());
      await pickType(page, type);
      await pickResource(page, `${type} 1`);
      await addFilter(page).click();
      await typeField(page, 1).locator('..').click();
      await expect.poll(() => optionNames(page)).toEqual(available);
    });
  }

  test('removes the next lines when the type of a middle line changes', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await pickType(page, labelHostCategory);
    await pickResource(page, `${labelHostCategory} 1`);
    await addFilter(page).click();
    await pickType(page, labelHostGroup, 1);
    await pickResource(page, `${labelHostGroup} 1`, 1);
    await addFilter(page).click();
    await pickType(page, labelServiceCategory, 2);
    await pickResource(page, `${labelServiceCategory} 1`, 2);
    await expect(page.getByTestId(labelResourceType)).toHaveCount(3);
    await pickType(page, labelServiceGroup, 1);
    await expect(page.getByTestId(labelResourceType)).toHaveCount(2);
  });

  test('disables the Add filter button once a meta service is picked', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await pickType(page, labelMetaService);
    await pickResource(page, 'Meta service 0');
    await resourceField(page).blur();
    await expect(addFilter(page)).toBeDisabled();
  });

  test('reloads the next lines resources when a resource changes', async ({
    mount,
    page
  }) => {
    await mount(storyId, props());
    await pickType(page, labelHostGroup);
    await pickResource(page, 'Host Group 0');
    await addFilter(page).click();
    await pickType(page, labelHost, 1);
    await pickResource(page, 'Host 0', 1);
    await addFilter(page).click();
    await pickType(page, labelService, 2);
    await pickResource(page, 'Service 0', 2);
    const hosts = mocks.hosts.urls.length;
    const services = mocks.services.urls.length;
    await pickResource(page, 'Host Group 1');
    await expect.poll(() => mocks.hosts.urls.length).toBeGreaterThan(hosts);
    await expect
      .poll(() => mocks.services.urls.length)
      .toBeGreaterThan(services);
  });

  const forcedService = {
    inputProps: {
      defaultResourceTypes: ['host', 'service'],
      forcedResourceType: 'service',
      restrictedResourceTypes: ['host', 'meta-service']
    },
    singleResourceSelection: true
  };

  test('hides the service line while a meta service is the type', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, props(forcedService));
    await pickType(page, labelMetaService);
    await expect(page.getByTestId(labelResourceType)).toHaveCount(1);
    await pickType(page, labelHost);
    await expect(page.getByTestId(labelResourceType)).toHaveCount(2);
    await expect(typeField(page, 1)).toHaveValue('service');
    await expectThemedScreenshot(page, 'widget-resources-forced-service');
  });

  test('unselects the service when a meta service is picked then host again', async ({
    mount,
    page
  }) => {
    await mount(storyId, props(forcedService));
    await pickResource(page, 'Host 0');
    await pickResource(page, 'Service 0', 1);
    await pickType(page, labelMetaService);
    await pickResource(page, 'Meta service 0');
    await expect(page.getByTestId(labelResourceType)).toHaveCount(1);
    await pickType(page, labelHost);
    await expect(page.getByTestId(labelResourceType)).toHaveCount(2);
    await expect(page.getByText('Host 0')).toHaveCount(0);
    await expect(page.getByText('Service 0')).toHaveCount(0);
  });

  test('displays a resource type as a single autocomplete when its conditions are met', async ({
    mount,
    page
  }) => {
    await mount(
      storyId,
      props({
        inputProps: {
          forceSingleAutocompleteConditions: {
            conditions: [
              {
                matches: 'value',
                method: 'equals',
                target: 'options',
                when: 'options.property'
              }
            ],
            resourceType: 'host'
          },
          restrictedResourceTypes: ['host', 'service']
        }
      })
    );
    await pickType(page, labelHost);
    await pickResource(page, 'Host 0');
    await addFilter(page).click();
    await pickType(page, labelService, 1);
    await resourceField(page, 1).click();
    await page.getByRole('option', { name: 'Service 0' }).click();
    await page.getByRole('option', { name: 'Service 1' }).click();
    await resourceField(page, 1).blur();
    await expect(resourceField(page, 0)).toHaveValue('Host 0');
    await expect(resourceField(page, 1)).toHaveValue('');
  });
});

test.describe('Regex resources', () => {
  const regexProps = props({
    inputProps: {
      allowRegexOnResourceTypes: ['host'],
      restrictedResourceTypes: ['host', 'service']
    }
  });
  const activateRegex = (page: Page) =>
    page.getByTestId(`${labelActivateRegex}-host`);

  test('filters the services with the host regex', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, regexProps);
    await pickType(page, labelHost);
    await activateRegex(page).click();
    await page.locator('[id="EnterRegexhost"]').fill('^Cen');
    await addFilter(page).click();
    await pickType(page, labelService, 1);
    await resourceField(page).click();
    await expect
      .poll(() => {
        const url = mocks.services.urls.at(-1);

        return url && new URL(url).searchParams.get('search');
      })
      .toBe('{"$or":[{"host.name":{"$rg":"^Cen"}}]}');
    await expectThemedScreenshot(page, 'widget-resources-regex');
  });

  test('asks before leaving the regex mode once a regex is typed', async ({
    mount,
    page
  }) => {
    await mount(storyId, regexProps);
    await pickType(page, labelHost);
    await activateRegex(page).click();
    await page.locator('[id="EnterRegexhost"]').fill('Host');
    await page.getByTestId(`${labelDeactivateRegex}-host`).click();
    await expect(
      page.getByText(labelDoYouWantToLeaveTheRegexMode)
    ).toBeVisible();
    await expect(
      page.getByText(labelYourChangesWillNotBeSavedIfYouSwitchRegexMode)
    ).toBeVisible();
  });

  const askToLeaveClassicMode = async (page: Page): Promise<void> => {
    await pickType(page, labelHost);
    await pickResource(page, 'Host 0');
    await activateRegex(page).click();
    await expect(
      page.getByText(labelDoYouWantToLeaveTheClassicMode)
    ).toBeVisible();
    await expect(
      page.getByText(labelYourChangesWillNotBeSavedIfYouSwitchClassicMode)
    ).toBeVisible();
  };

  test('keeps the selected host when the user stays in the classic mode', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    await mount(storyId, regexProps);
    await askToLeaveClassicMode(page);
    await expectThemedScreenshot(page, 'widget-resources-leave-classic-mode');
    await page.getByText(labelStay, { exact: true }).click();
    await expect(page.getByText('Host 0')).toBeVisible();
  });

  test('drops the selected host when the user leaves the classic mode', async ({
    mount,
    page
  }) => {
    await mount(storyId, regexProps);
    await askToLeaveClassicMode(page);
    await page.getByText(labelLeave, { exact: true }).click();
    await expect(page.getByText('Host 0')).toHaveCount(0);
  });
});
