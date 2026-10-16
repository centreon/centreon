import type { Locator, Page } from '@playwright/test';
import pluralize from 'pluralize';

import { panelDataTestIds } from '../../www/front_src/src/Configuration/ConfigurationBase/Panel/dataTestIds';
import {
  labelActions,
  labelAlias,
  labelClear,
  labelClose,
  labelDelete,
  labelDeleteResource,
  labelDisable,
  labelDisabled,
  labelDuplicate,
  labelDuplicateResource,
  labelDuplications,
  labelEnable,
  labelEnableDisable,
  labelFilters,
  labelMoreActions,
  labelName,
  labelResourceDeleted,
  labelResourceDisabled,
  labelResourceDuplicated,
  labelResourceEnabled,
  labelSave,
  labelSearch
} from '../../www/front_src/src/Configuration/ConfigurationBase/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Configuration/ConfigurationBase/Specs (Layout, Filters,
// Actions, Modal, Panel and StaticColumns suites), for hosts and host groups.
const storyId = 'ConfigurationBase/ConfigurationBase/Default';
const labelHostTemplate = 'Host template';

interface ResourceData {
  endpoints: Record<
    'create' | 'delete' | 'disable' | 'duplicate' | 'enable' | 'getAll' | 'one',
    string
  >;
  labels: Record<
    'deleteMany' | 'deleteOne' | 'duplicateMany' | 'duplicateOne',
    string
  >;
  listing: unknown;
}
interface ConfigurationBaseData {
  defaultFiltersPanelWidth: number;
  defaultPanelWidths: Record<string, number>;
  groups: Array<string>;
  hostTemplates: unknown;
  hostTemplatesEndpoint: string;
  inputs: Array<{ fieldName: string; label: string }>;
  maxPanelWidth: number;
  resources: Record<string, ResourceData>;
}
interface Mocks {
  create: MockedEndpoint;
  delete: MockedEndpoint;
  deleteOne: MockedEndpoint;
  details: MockedEndpoint;
  disable: MockedEndpoint;
  duplicate: MockedEndpoint;
  enable: MockedEndpoint;
  listing: MockedEndpoint;
  update: MockedEndpoint;
}

let data: ConfigurationBaseData;

// The app endpoints are relative to the API base: mockApi matches the path end.
const path = (endpoint: string) => endpoint.replace(/^\//, '');

const mockConfigurationApi = async (
  mockApi: MockApi,
  resourceType: string
): Promise<Mocks> => {
  const { endpoints, listing } = data.resources[resourceType];
  const resource = resourceType.replace(' ', '_');
  const details = {
    alias: `${resourceType} 1 alias`,
    coordinates: '-20.40,13,12',
    name: `${resource} 1`
  };
  await mockApi({
    endpoint: path(data.hostTemplatesEndpoint),
    response: data.hostTemplates
  });

  return {
    create: await mockApi({
      endpoint: path(endpoints.create),
      method: 'POST',
      response: details
    }),
    delete: await mockApi({
      endpoint: path(endpoints.delete),
      method: 'POST',
      response: { code: 200, status: 'ok' }
    }),
    deleteOne: await mockApi({
      endpoint: path(endpoints.one),
      method: 'DELETE',
      response: { code: 200, status: 'ok' }
    }),
    details: await mockApi({
      endpoint: path(endpoints.one),
      response: details
    }),
    disable: await mockApi({
      endpoint: path(endpoints.disable),
      method: 'POST',
      response: {
        results: [{ href: '/resources/1', message: null, status: 204 }]
      }
    }),
    duplicate: await mockApi({
      endpoint: path(endpoints.duplicate),
      method: 'POST',
      response: { code: 200, status: 'ok' }
    }),
    enable: await mockApi({
      endpoint: path(endpoints.enable),
      method: 'POST',
      response: {
        results: [{ href: '/resources/1', message: null, status: 204 }]
      }
    }),
    listing: await mockApi({
      endpoint: path(endpoints.getAll),
      response: listing
    }),
    update: await mockApi({
      endpoint: path(endpoints.one),
      method: 'PUT',
      response: {}
    })
  };
};

const lastSearch = (mocks: Mocks) => () =>
  JSON.parse(mocks.listing.urls.at(-1)?.searchParams.get('search') ?? 'null');
const selectRows = async (component: Locator) => {
  await component.getByLabel('Select row 1', { exact: true }).click();
  // Row 2 is deactivated: its checkbox is greyed but still clickable.
  await component
    .getByLabel('Select row 2', { exact: true })
    .click({ force: true });
  await component.getByLabel('Select row 3', { exact: true }).click();
};
const openMoreActions = (component: Locator) =>
  component.getByTestId(labelMoreActions).first().click();
// Form fields carry their test id on both the wrapper and the input.
const field = (scope: Locator | Page, testId: string) =>
  scope.locator(`:is(input, textarea)[data-testid="${testId}"]`);
const panelSurface = (page: Page) =>
  page
    .getByTestId(panelDataTestIds.content)
    .locator('xpath=ancestor::*[contains(@class, "MuiPaper-root")][1]');
const width = (locator: Locator) =>
  locator.evaluate((element) => element.getBoundingClientRect().width);

test.beforeEach(async ({ loadStoryData }) => {
  data = await loadStoryData<ConfigurationBaseData>(
    'ConfigurationBase/ConfigurationBase/Data',
    'configurationBaseData'
  );
});

for (const resourceType of ['host', 'host group']) {
  const resource = resourceType.replace(' ', '_');
  const resourceName = `${resource} 1`;
  const capitalized = `${resourceType[0].toUpperCase()}${resourceType.slice(1)}`;

  test.describe(`${resourceType}`, () => {
    let mocks: Mocks;

    const setUp = async (
      mount: (id: string, props?: object) => Promise<Locator>,
      props: object = {}
    ) => {
      const component = await mount(storyId, { resourceType, ...props });
      await expect(
        component.getByText(`${resource} 0`, { exact: true })
      ).toBeVisible();

      return component;
    };

    test.describe('layout', () => {
      test.beforeEach(async ({ mockApi }) => {
        mocks = await mockConfigurationApi(mockApi, resourceType);
      });

      test('renders the layout with all components', async ({
        expectThemedScreenshot,
        mount
      }) => {
        const component = await setUp(mount);

        await expect(
          component.getByText(pluralize(capitalized)).first()
        ).toBeVisible();
        await expectThemedScreenshot(
          component,
          `configuration-${resource}-layout`
        );
      });

      test('displays the static columns, the resource columns and more actions', async ({
        mount
      }) => {
        const component = await setUp(mount);

        for (const label of [
          labelEnableDisable,
          labelActions,
          labelName,
          labelAlias
        ]) {
          await expect(
            component.getByText(label, { exact: true }).first()
          ).toBeVisible();
        }
        for (const testId of [
          `${labelDuplicate}_1`,
          `${labelDelete}_1`,
          `${labelEnableDisable}_1`,
          labelMoreActions
        ]) {
          await expect(component.getByTestId(testId)).toBeVisible();
        }
      });

      test('opens the advanced filters', async ({
        expectThemedScreenshot,
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await expect(
          component.locator('div[data-testid="search-bar"]')
        ).toBeVisible();
        await component.getByTestId(labelFilters).click();

        const filters = page.getByTestId('advanced-filters');
        await expect(filters).toBeVisible();
        await expectThemedScreenshot(
          filters,
          `configuration-${resource}-advanced-filters`
        );
      });
    });

    test.describe('filters', () => {
      test.beforeEach(async ({ mockApi }) => {
        mocks = await mockConfigurationApi(mockApi, resourceType);
      });

      const advancedFilters = (page: Page) =>
        page.getByTestId('advanced-filters');
      const openFilters = async (component: Locator) => {
        await component.getByTestId(labelFilters).click();
        await expect(advancedFilters(component.page())).toBeVisible();
      };
      const fillFilters = async (page: Page) => {
        await field(advancedFilters(page), labelName).fill(`${resourceType} 1`);
        await field(advancedFilters(page), labelAlias).fill(
          `${resourceType} alias 1`
        );
        await advancedFilters(page).getByTestId(labelDisabled).click();
      };
      const selectHostTemplate = async (page: Page) => {
        await advancedFilters(page).getByTestId(labelHostTemplate).click();
        await page
          .locator('.MuiAutocomplete-popper')
          .getByText('host template 1', { exact: true })
          .click();
      };

      test('searches by name from the search bar, debounced', async ({
        mount
      }) => {
        const component = await setUp(mount);
        await component.getByPlaceholder(labelSearch).fill(`${resourceType} 1`);

        await expect.poll(lastSearch(mocks)).toEqual({
          $and: [{ $or: [{ name: { $rg: `${resourceType} 1` } }] }]
        });
      });

      test('searches with the advanced filters', async ({ mount, page }) => {
        const component = await setUp(mount);
        await openFilters(component);
        await fillFilters(page);
        await advancedFilters(page).getByTestId(labelSearch).click();

        await expect.poll(lastSearch(mocks)).toEqual({
          $and: [
            { $or: [{ is_activated: { $eq: false } }] },
            { $or: [{ name: { $rg: `${resourceType} 1` } }] },
            { $or: [{ alias: { $rg: `${resourceType} alias 1` } }] }
          ]
        });
      });

      test('clears the filters', async ({ mount, page }) => {
        const component = await setUp(mount);
        await openFilters(component);
        await fillFilters(page);
        await advancedFilters(page).getByTestId(labelClear).click();

        await expect.poll(lastSearch(mocks)).toEqual({ $and: [] });
      });

      test.describe('single connected autocomplete', () => {
        const props = { filters: 'singleConnectedAutocomplete' };

        test('searches by the selected id', async ({ mount, page }) => {
          const component = await setUp(mount, props);
          await openFilters(component);
          await selectHostTemplate(page);
          await advancedFilters(page).getByTestId(labelSearch).click();

          await expect.poll(lastSearch(mocks)).toEqual({
            $and: [{ $or: [{ 'host_template.id': { $eq: 1 } }] }]
          });
        });

        test('drops the filter once the value is cleared', async ({
          mount,
          page
        }) => {
          const component = await setUp(mount, props);
          await openFilters(component);
          await selectHostTemplate(page);
          const autocomplete =
            advancedFilters(page).getByTestId(labelHostTemplate);
          await advancedFilters(page)
            .locator('.MuiAutocomplete-root')
            .filter({ has: page.getByTestId(labelHostTemplate) })
            .locator('button[title="Clear"]')
            .click({ force: true });
          await expect(autocomplete).toHaveValue('');
          await advancedFilters(page).getByTestId(labelSearch).click();

          await expect.poll(lastSearch(mocks)).toEqual({ $and: [] });
        });

        test('keeps the selected value when the listing is mounted again', async ({
          mount,
          page
        }) => {
          let component = await setUp(mount, props);
          await openFilters(component);
          await selectHostTemplate(page);
          await expect(
            advancedFilters(page).getByTestId(labelHostTemplate)
          ).toHaveValue('host template 1');

          component = await setUp(mount, props);
          await openFilters(component);
          await expect(
            advancedFilters(page).getByTestId(labelHostTemplate)
          ).toHaveValue('host template 1');
        });
      });

      test('opens the advanced filters at the default or declared width', async ({
        mount,
        page
      }) => {
        // theme.spacing: 8 px a unit.
        let component = await setUp(mount);
        await openFilters(component);
        await expect(advancedFilters(page)).toHaveCSS(
          'width',
          `${data.defaultFiltersPanelWidth * 8}px`
        );

        component = await setUp(mount, { filtersPanelWidth: 60 });
        await openFilters(component);
        await expect(advancedFilters(page)).toHaveCSS('width', `${60 * 8}px`);
      });

      test('hides the advanced filters when only the name is filterable', async ({
        mount
      }) => {
        const component = await setUp(mount, { filters: 'onlyName' });

        await expect(component.getByTestId(labelFilters)).toHaveCount(0);
      });
    });

    test.describe('actions', () => {
      test.beforeEach(async ({ mockApi }) => {
        mocks = await mockConfigurationApi(mockApi, resourceType);
      });

      const plural = pluralize(capitalized);

      test('enables more actions with a selection', async ({ mount }) => {
        const component = await setUp(mount);
        await expect(
          component.getByTestId(labelMoreActions).first()
        ).toBeDisabled();

        await selectRows(component);
        await expect(
          component.getByTestId(labelMoreActions).first()
        ).toBeEnabled();
      });

      test('deletes one resource after confirmation', async ({
        expectThemedScreenshot,
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await component.getByTestId(`${labelDelete}_1`).click();
        const dialog = page.getByRole('dialog');
        await expect(
          dialog.getByText(labelDeleteResource(resourceType))
        ).toBeVisible();
        await expect(
          dialog.getByText(data.resources[resourceType].labels.deleteOne)
        ).toBeVisible();
        await expectThemedScreenshot(
          dialog,
          `configuration-${resource}-delete-one`
        );
        await page.getByTestId('confirm').click();

        await expect.poll(() => mocks.deleteOne.urls.length).toBe(1);
        await expect(
          page.getByText(labelResourceDeleted(capitalized))
        ).toBeVisible();
      });

      test('deletes the selected resources after confirmation', async ({
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await selectRows(component);
        await openMoreActions(component);
        await page.getByTestId(labelDelete).first().click();
        const dialog = page.getByRole('dialog');
        await expect(
          dialog.getByText(labelDeleteResource(pluralize(resourceType)))
        ).toBeVisible();
        await expect(
          dialog.getByText(data.resources[resourceType].labels.deleteMany)
        ).toBeVisible();
        await page.getByTestId('confirm').click();

        await expect
          .poll(() => mocks.delete.bodies)
          .toEqual([{ ids: [1, 2, 3] }]);
        await expect(
          page.getByText(labelResourceDeleted(plural))
        ).toBeVisible();
      });

      test('duplicates one resource after confirmation', async ({
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await component.getByTestId(`${labelDuplicate}_1`).click();
        const dialog = page.getByRole('dialog');
        await expect(
          dialog.getByText(labelDuplicateResource(resourceType))
        ).toBeVisible();
        await expect(
          dialog.getByText(data.resources[resourceType].labels.duplicateOne)
        ).toBeVisible();
        await expect(dialog.getByText(labelDuplications).first()).toBeVisible();
        await page.getByTestId('confirm').click();

        await expect
          .poll(() => mocks.duplicate.bodies)
          .toEqual([{ ids: [1], nb_duplicates: 1 }]);
        await expect(
          page.getByText(labelResourceDuplicated(capitalized))
        ).toBeVisible();
      });

      for (const duplications of [1, 4]) {
        test(`duplicates the selected resources ${duplications} time(s)`, async ({
          mount,
          page
        }) => {
          const component = await setUp(mount);
          await selectRows(component);
          await openMoreActions(component);
          await page.getByTestId(labelDuplicate).first().click();
          const dialog = page.getByRole('dialog');
          await expect(
            dialog.getByText(labelDuplicateResource(pluralize(resourceType)))
          ).toBeVisible();
          await expect(
            dialog.getByText(data.resources[resourceType].labels.duplicateMany)
          ).toBeVisible();
          const count = dialog.getByLabel(labelDuplications).first();
          await expect(count).toHaveValue('1');
          await count.fill(String(duplications));
          await page.getByTestId('confirm').click();

          await expect
            .poll(() => mocks.duplicate.bodies)
            .toEqual([{ ids: [1, 2, 3], nb_duplicates: duplications }]);
          await expect(
            page.getByText(labelResourceDuplicated(plural))
          ).toBeVisible();
        });
      }

      for (const [action, label, mockName, rowId, success] of [
        ['enables', labelEnable, 'enable', 2, labelResourceEnabled],
        ['disables', labelDisable, 'disable', 1, labelResourceDisabled]
      ] as const) {
        test(`${action} one resource from its toggle`, async ({
          mount,
          page
        }) => {
          const component = await setUp(mount);
          await component.getByTestId(`${labelEnableDisable}_${rowId}`).click();

          await expect
            .poll(() => mocks[mockName].bodies)
            .toEqual([{ ids: [rowId] }]);
          await expect(page.getByText(success(capitalized))).toBeVisible();
        });

        test(`${action} the selected resources`, async ({ mount, page }) => {
          const component = await setUp(mount);
          await selectRows(component);
          await openMoreActions(component);
          await page.getByLabel(label, { exact: true }).first().click();

          await expect
            .poll(() => mocks[mockName].bodies)
            .toEqual([{ ids: [1, 2, 3] }]);
          await expect(page.getByText(success(plural))).toBeVisible();
        });
      }
    });

    test.describe('modal', () => {
      test.beforeEach(async ({ mockApi }) => {
        mocks = await mockConfigurationApi(mockApi, resourceType);
      });

      const modal = (page: Page) => page.getByRole('dialog');
      const submit = (page: Page) =>
        modal(page).locator('button[data-testid="submit"]');
      const fillInputs = async (page: Page) => {
        for (const { label } of data.inputs) {
          await field(modal(page), label).fill(`${label} abc`);
        }
      };
      const sentValues = {
        alias: 'Alias abc',
        coordinates: 'Coordinates abc',
        name: 'Name abc'
      };

      test('opens an empty creation modal', async ({
        expectThemedScreenshot,
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await component.getByTestId('add-resource').click();

        await expect(
          modal(page).getByText(`Add a ${resourceType}`)
        ).toBeVisible();
        await expect(submit(page)).toHaveText(labelSave);
        await expect(submit(page)).toBeDisabled();
        // Opt-in: a module that asks for nothing keeps the modal.
        await expect(page.getByTestId(panelDataTestIds.content)).toHaveCount(0);
        for (const group of data.groups) {
          await expect(modal(page).getByText(group)).toBeVisible();
        }
        for (const { label } of data.inputs) {
          await expect(field(modal(page), label)).toHaveValue('');
        }
        await expectThemedScreenshot(
          modal(page),
          `configuration-${resource}-creation-modal`
        );
      });

      test('creates a resource', async ({ mount, page }) => {
        const component = await setUp(mount);
        await component.getByTestId('add-resource').click();
        await fillInputs(page);
        await submit(page).click();

        await expect.poll(() => mocks.create.bodies).toEqual([sentValues]);
      });

      test('opens the edition modal on the API values', async ({
        mount,
        page
      }) => {
        const component = await setUp(mount);
        await component.getByText(resourceName, { exact: true }).click();

        await expect(
          modal(page).getByText(`Modify a ${resourceType}`)
        ).toBeVisible();
        await expect(submit(page)).toBeDisabled();
        const details = {
          alias: `${resourceType} 1 alias`,
          coordinates: '-20.40,13,12',
          name: resourceName
        };
        for (const { fieldName, label } of data.inputs) {
          await expect(field(modal(page), label)).toHaveValue(
            details[fieldName as keyof typeof details]
          );
        }
      });

      test('updates a resource', async ({ mount, page }) => {
        const component = await setUp(mount);
        await component.getByText(resourceName, { exact: true }).click();
        await expect(field(modal(page), labelName)).toHaveValue(resourceName);
        await fillInputs(page);
        await submit(page).click();

        await expect.poll(() => mocks.update.bodies).toEqual([sentValues]);
      });
    });

    test.describe('panel', () => {
      test.beforeEach(async ({ mockApi }) => {
        mocks = await mockConfigurationApi(mockApi, resourceType);
      });

      const header = (page: Page) => page.getByTestId(panelDataTestIds.header);
      const panelButton = (page: Page, testId: string) =>
        page.locator(`button[data-testid="${testId}"]`);
      const location = (page: Page) => page.getByTestId('location');
      const openPanel = (
        mount: (id: string, props?: object) => Promise<Locator>,
        props: object = {}
      ) => setUp(mount, { formVariant: 'panel', ...props });
      const openForEdition = async (component: Locator) => {
        await component.getByText(resourceName, { exact: true }).click();
        await expect(field(component.page(), labelName)).toHaveValue(
          resourceName
        );
      };
      const sentValues = {
        alias: 'Alias abc',
        coordinates: 'Coordinates abc',
        name: 'Name abc'
      };
      const fillInputs = async (page: Page) => {
        for (const { label } of data.inputs) {
          await field(page, label).fill(`${label} abc`);
        }
      };

      test.describe('creation', () => {
        test('opens the panel from the Add button, actions in its header', async ({
          expectThemedScreenshot,
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await component.getByTestId('add-resource').click();

          await expect(header(page)).toHaveText(`Add a ${resourceType}`);
          await expect(panelButton(page, panelDataTestIds.save)).toBeDisabled();
          // The actions are in the header, so the form keeps none.
          await expect(
            page.locator('button[data-testid="submit"]')
          ).toHaveCount(0);
          await expect(
            page.locator('button[data-testid="cancel"]')
          ).toHaveCount(0);
          // Nothing to reset, enable, duplicate or delete before it exists.
          for (const testId of [
            panelDataTestIds.reset,
            panelDataTestIds.enable,
            panelDataTestIds.duplicate,
            panelDataTestIds.delete
          ]) {
            await expect(page.getByTestId(testId)).toHaveCount(0);
          }
          for (const group of data.groups) {
            await expect(page.getByText(group)).toBeVisible();
          }
          for (const { label } of data.inputs) {
            await expect(field(page, label)).toHaveValue('');
          }
          if (resourceType === 'host') {
            // The panel renders the same pixels whatever the resource type.
            await expectThemedScreenshot(page, 'configuration-creation-panel');
          }
        });

        test('runs from the top of the page to its bottom, over the listing', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          const listing = page.locator('.MuiTable-root');
          const listingWidth = await width(listing);
          await component.getByTestId('add-resource').click();
          await expect(
            page.getByTestId(panelDataTestIds.content)
          ).toBeVisible();

          const pageBox = await page.locator('#page').evaluate((element) => ({
            position: getComputedStyle(element).position,
            rect: element.getBoundingClientRect().toJSON()
          }));
          expect(pageBox.position).toBe('relative');
          await expect
            .poll(() =>
              panelSurface(page).evaluate((element) => {
                const { bottom, right, top } = element.getBoundingClientRect();

                return { bottom, right, top };
              })
            )
            .toEqual({
              bottom: pageBox.rect.bottom,
              right: pageBox.rect.right,
              top: pageBox.rect.top
            });
          // Over the listing, which keeps its width.
          await expect.poll(() => width(listing)).toBe(listingWidth);
        });

        test('keeps the panel and its actions inside a narrow page', async ({
          mount,
          page
        }) => {
          await page.setViewportSize({ height: 590, width: 700 });
          const component = await openPanel(mount);
          await component.getByTestId('add-resource').click();

          const right = (locator: Locator) =>
            locator.evaluate(
              (element) => element.getBoundingClientRect().right
            );
          await expect
            .poll(() => right(page.getByTestId(panelDataTestIds.content)))
            .toBeLessThanOrEqual(700);
          expect(
            await right(panelButton(page, panelDataTestIds.save))
          ).toBeLessThanOrEqual(700);
          // Narrower than asked: the clamp fired, rather than 720 fitting.
          expect(
            await width(page.getByTestId(panelDataTestIds.content))
          ).toBeLessThan(720);
        });

        for (const viewport of [1920, 1512, 1440, 1280]) {
          test(`opens at the design width on a ${viewport}px screen`, async ({
            mount,
            page
          }) => {
            await page.setViewportSize({ height: 900, width: viewport });
            const component = await openPanel(mount);
            await component.getByTestId('add-resource').click();

            // The surface itself: a scrollbar narrows the content inside it.
            await expect
              .poll(() => width(panelSurface(page)))
              .toBe(data.defaultPanelWidths[viewport]);
          });
        }

        test('reopens at the width it was last dragged to', async ({
          mount,
          page
        }) => {
          await page.setViewportSize({ height: 900, width: 1920 });
          const component = await openPanel(mount, { panelWidth: 1000 });
          await component.getByTestId('add-resource').click();
          // Not the 1400 this screen opens at when nothing was ever dragged.
          await expect.poll(() => width(panelSurface(page))).toBe(1000);

          await page.getByLabel(labelClose, { exact: true }).click();
          await component.getByTestId('add-resource').click();
          await expect.poll(() => width(panelSurface(page))).toBe(1000);
        });

        test('opens no wider than the design allows', async ({
          mount,
          page
        }) => {
          await page.setViewportSize({ height: 900, width: 1920 });
          const component = await openPanel(mount, { formPanelWidth: 2000 });
          await component.getByTestId('add-resource').click();

          await expect
            .poll(() => width(panelSurface(page)))
            .toBe(data.maxPanelWidth);
        });

        test('creates a resource from the save action', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await component.getByTestId('add-resource').click();
          await fillInputs(page);
          await panelButton(page, panelDataTestIds.save).click();

          await expect.poll(() => mocks.create.bodies).toEqual([sentValues]);
        });
      });

      test.describe('edition', () => {
        test('opens the panel on a listing row, on the API values', async ({
          expectThemedScreenshot,
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);

          await expect(header(page)).toHaveText(resourceName);
          await expect(panelButton(page, panelDataTestIds.save)).toBeDisabled();
          await expect(
            panelButton(page, panelDataTestIds.duplicate)
          ).toBeVisible();
          await expect(
            panelButton(page, panelDataTestIds.delete)
          ).toBeVisible();
          // A module declaring no `baseEndpoint` reads the default one.
          expect(mocks.details.urls[0].pathname).toContain(
            '/api/latest/configuration/'
          );
          await expect(field(page, labelAlias)).toHaveValue(
            `${resourceType} 1 alias`
          );
          if (resourceType === 'host') {
            await expectThemedScreenshot(page, 'configuration-edition-panel');
          }
        });

        test('updates a resource from the save action', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          await fillInputs(page);
          await panelButton(page, panelDataTestIds.save).click();

          await expect.poll(() => mocks.update.bodies).toEqual([sentValues]);
        });

        test('restores the loaded values from the reset action', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          const reset = panelButton(page, panelDataTestIds.reset);
          const save = panelButton(page, panelDataTestIds.save);
          await expect(reset).toBeDisabled();

          await field(page, labelName).fill('edited');
          await expect(reset).toBeEnabled();
          await expect(save).toBeEnabled();
          await reset.click();
          // Discarding edits is confirmed, as deleting and closing are.
          await page.getByTestId('confirm').click();

          await expect(field(page, labelName)).toHaveValue(resourceName);
          await expect(reset).toBeDisabled();
          await expect(save).toBeDisabled();
        });

        test('disables the open resource from the panel header', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          const toggle = page
            .getByTestId(panelDataTestIds.enable)
            .locator('input');
          await expect(toggle).toBeChecked();
          await toggle.click();

          await expect.poll(() => mocks.disable.bodies).toEqual([{ ids: [1] }]);
          await expect(toggle).not.toBeChecked();
        });

        test('puts the toggle back when the request is refused', async ({
          mockApi,
          mount,
          page
        }) => {
          // Registered last, so it wins over the succeeding mock.
          const refused = await mockApi({
            endpoint: path(data.resources[resourceType].endpoints.disable),
            method: 'POST',
            response: {
              results: [
                {
                  href: '/resources/1',
                  message: 'Resource in use',
                  status: 409
                }
              ]
            }
          });
          const component = await openPanel(mount);
          await openForEdition(component);
          const toggle = page
            .getByTestId(panelDataTestIds.enable)
            .locator('input');
          await expect(toggle).toBeChecked();
          await toggle.click();

          await expect.poll(() => refused.urls.length).toBe(1);
          await expect(toggle).toBeChecked();
        });

        test('follows the listing when another row is clicked', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          await expect(header(page)).toHaveText(resourceName);

          // No detail response for this one: the panel has only the row.
          const otherResource = `${resource} 3`;
          await component.getByText(otherResource, { exact: true }).click();
          await expect(header(page)).toHaveText(otherResource);
        });

        test('asks before another row takes the panel from unsaved edits', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          await field(page, labelName).fill('edited');
          await component.getByText(`${resource} 3`, { exact: true }).click();

          await expect(page.getByRole('dialog')).toBeVisible();
          await expect(header(page)).toHaveText(resourceName);
        });

        test('closes and clears the URL when the open resource is deleted', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          await expect(location(page)).toHaveValue(/id=1/);

          await panelButton(page, panelDataTestIds.delete).click();
          // Scoped: the listing row behind it carries the same text.
          await expect(
            page.getByRole('dialog').getByText(resourceName).first()
          ).toBeVisible();
          await page.getByTestId('confirm').click();

          await expect.poll(() => mocks.deleteOne.urls.length).toBe(1);
          await expect(page.getByTestId(panelDataTestIds.content)).toHaveCount(
            0
          );
          await expect(location(page)).toHaveValue('/');
        });

        test('stays open when a delete does not name its resource', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          // Rows are labelled by id; only activated ones are selectable.
          await component.getByLabel('Select row 3', { exact: true }).click();
          await component.getByLabel('Select row 5', { exact: true }).click();
          await openMoreActions(component);
          await page.getByTestId(labelDelete).first().click();
          await page.getByTestId('confirm').click();

          await expect.poll(() => mocks.delete.urls.length).toBe(1);
          await expect(
            page.getByTestId(panelDataTestIds.content)
          ).toBeVisible();
          await expect(location(page)).toHaveValue(/id=1/);
        });

        test('closes from its close button', async ({ mount, page }) => {
          const component = await openPanel(mount);
          await openForEdition(component);
          await expect(
            page.getByTestId(panelDataTestIds.content)
          ).toBeVisible();
          await page.getByLabel(labelClose, { exact: true }).click();

          await expect(page.getByTestId(panelDataTestIds.content)).toHaveCount(
            0
          );
        });
      });

      test.describe('deep linking', () => {
        test('opens the creation panel from the URL', async ({
          mount,
          page
        }) => {
          await openPanel(mount, { initialEntry: '/?mode=add' });

          await expect(header(page)).toHaveText(`Add a ${resourceType}`);
        });

        test('opens the panel on the resource the URL names', async ({
          mount,
          page
        }) => {
          await openPanel(mount, { initialEntry: '/?mode=edit&id=1' });

          await expect(header(page)).toHaveText(resourceName);
          await expect.poll(() => mocks.details.urls.length).toBeGreaterThan(0);
        });

        test('ignores the URL without edition nor details', async ({
          mount,
          page
        }) => {
          const component = await openPanel(mount, {
            actions: 'noEditNorDetails',
            initialEntry: '/?mode=edit&id=1'
          });

          // The URL is ignored, not the page.
          await expect(
            component.getByText(resourceName, { exact: true })
          ).toBeVisible();
          await expect(page.getByTestId(panelDataTestIds.content)).toHaveCount(
            0
          );
        });

        test('opens a read-only panel for details without edition', async ({
          mount,
          page
        }) => {
          await openPanel(mount, {
            actions: 'detailsOnly',
            initialEntry: '/?mode=edit&id=1'
          });

          await expect(
            page.getByTestId(panelDataTestIds.content)
          ).toBeVisible();
          await expect.poll(() => mocks.details.urls.length).toBeGreaterThan(0);
          for (const testId of [
            panelDataTestIds.save,
            panelDataTestIds.reset,
            panelDataTestIds.duplicate,
            panelDataTestIds.delete,
            panelDataTestIds.enable
          ]) {
            await expect(page.getByTestId(testId)).toHaveCount(0);
          }
        });
      });
    });
  });
}

test.describe('static columns', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockConfigurationApi(mockApi, 'host');
  });

  const setUp = async (
    mount: (id: string, props?: object) => Promise<Locator>,
    actions: string
  ) => {
    const component = await mount(storyId, { actions });
    await expect(component.getByText('host 0', { exact: true })).toBeVisible();

    return component;
  };

  test('offers neither column without row actions', async ({ mount }) => {
    const component = await setUp(mount, 'editOnly');

    await expect(
      component.getByText(labelActions, { exact: true })
    ).toHaveCount(0);
    await expect(
      component.getByText(labelEnableDisable, { exact: true })
    ).toHaveCount(0);
  });

  test('offers the actions column, not the toggle, for delete alone', async ({
    mount
  }) => {
    const component = await setUp(mount, 'deleteOnly');

    await expect(
      component.getByText(labelActions, { exact: true }).first()
    ).toBeVisible();
    await expect(
      component.getByText(labelEnableDisable, { exact: true })
    ).toHaveCount(0);
  });

  test('hides the toggle on rows the predicate refuses', async ({ mount }) => {
    const component = await setUp(mount, 'toggleOnFirst');

    await expect(
      component.getByTestId(`${labelEnableDisable}_1`)
    ).toBeAttached();
    await expect(component.getByTestId(`${labelEnableDisable}_2`)).toHaveCount(
      0
    );
  });

  test('disables those rows instead without write access', async ({
    mount
  }) => {
    const component = await setUp(mount, 'toggleOnFirstWithoutWriteAccess');

    await expect(
      component.getByTestId(`${labelEnableDisable}_1`).locator('input')
    ).toBeEnabled();
    await expect(
      component.getByTestId(`${labelEnableDisable}_2`).locator('input')
    ).toBeDisabled();
  });

  test('renders a module row action, filtered by its visibility', async ({
    mount
  }) => {
    const component = await setUp(mount, 'rowAction');

    await expect(component.getByTestId('custom-action_1')).toBeAttached();
    await expect(component.getByTestId('custom-action_2')).toHaveCount(0);
  });
});
