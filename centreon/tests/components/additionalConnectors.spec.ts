import type { Locator, Page } from '@playwright/test';

import connector from '../../cypress/fixtures/ACC/additionalConnector.json';
import connectors from '../../cypress/fixtures/ACC/additionalConnectors.json';
import pollers from '../../cypress/fixtures/ACC/pollers-vmware.json';
import { ParameterKeys } from '../../www/front_src/src/Configuration/AdditionnalConnectors/models';
import {
  labelAddAnAdditionalConfiguration,
  labelAdditionalConnectorCreated,
  labelAdditionalConnectorUpdated,
  labelAddvCenterESX,
  labelAteastOnePollerIsRequired,
  labelCancel,
  labelClear,
  labelCreationDate,
  labelCreator,
  labelDescription,
  labelInvalidPortNumber,
  labelLastUpdate,
  labelModifyConnectorConfiguration,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelPoller,
  labelRemoveVCenterESX,
  labelRequired,
  labelSave,
  labelSearch,
  labelSelectPollers,
  labelType,
  labelUpdateBy,
  labelVcenterNameMustBeUnique,
  labelvCenterESX
} from '../../www/front_src/src/Configuration/AdditionnalConnectors/translatedLabels';
import { maskedPassword } from '../../www/front_src/src/Configuration/AdditionnalConnectors/utils';
import { expect, test } from './fixtures';

// Migrated from Configuration/AdditionnalConnectors/Specs (Listing, Filters
// and Modal suites).
const storyId = 'AdditionnalConnectors/AdditionnalConnectors/Default';
const connectorsEndpoint = 'configuration/additional-connector-configurations';

let listing: { urls: Array<URL> };
let creation: { bodies: Array<unknown> };
let update: { bodies: Array<unknown> };

test.use({ viewport: { height: 900, width: 1440 } });

test.beforeEach(async ({ mockApi }) => {
  listing = await mockApi({
    endpoint: connectorsEndpoint,
    response: connectors
  });
  creation = await mockApi({
    endpoint: connectorsEndpoint,
    method: 'POST',
    response: connector
  });
  await mockApi({ endpoint: `${connectorsEndpoint}/1`, response: connector });
  update = await mockApi({
    endpoint: `${connectorsEndpoint}/1`,
    method: 'PUT',
    response: {}
  });
  await mockApi({
    endpoint: 'configuration/monitoring-servers',
    response: pollers
  });
});

const mountConnectors = async (mount: (id: string) => Promise<Locator>) => {
  const component = await mount(storyId);
  await expect(component.getByText('VMWare1', { exact: true })).toBeVisible();
  await expect(
    component.getByText('Description for VMWare1', { exact: true })
  ).toBeVisible();

  return component;
};
const lastQuery = (key: string) => () =>
  listing.urls.at(-1)?.searchParams.get(key) ?? null;
const lastSearch = () => JSON.parse(lastQuery('search')() ?? 'null');

test.describe('listing', () => {
  test('displays the first page', async ({ expectThemedScreenshot, mount }) => {
    const component = await mountConnectors(mount);

    await expectThemedScreenshot(component, 'additional-connectors-listing');
  });

  test('changes the page size and the page', async ({ mount, page }) => {
    const component = await mountConnectors(mount);

    await component.locator('[id="Rows per page"]').click();
    await page
      .getByRole('listbox')
      .getByRole('option', { exact: true, name: '20' })
      .click();
    await expect.poll(lastQuery('limit')).toBe('20');
  });

  test('changes the page', async ({ mount }) => {
    const component = await mountConnectors(mount);
    await component.getByLabel('Next page').click();

    await expect.poll(lastQuery('page')).toBe('2');
  });

  test('sorts by each sortable column', async ({ mount }) => {
    const component = await mountConnectors(mount);

    for (const [label, id] of [
      [labelName, 'name'],
      [labelType, 'type'],
      [labelCreator, 'created_by'],
      [labelCreationDate, 'created_at'],
      [labelUpdateBy, 'updated_by'],
      [labelLastUpdate, 'updated_at']
    ]) {
      await component.getByLabel(`Column ${label}`).click();
      await expect
        .poll(() => JSON.parse(lastQuery('sort_by')() ?? '{}'))
        .toEqual({ [id]: 'desc' });
    }
  });
});

test.describe('filters', () => {
  const searchBar = (component: Locator) =>
    component.getByPlaceholder(labelSearch);
  // The advanced filters open in a portalled popover.
  const advancedFilters = (page: Page) => page.getByTestId('advanced-filters');
  const nameFilter = (page: Page) =>
    advancedFilters(page).locator(`input[data-testid="${labelName}"]`);
  const openFilters = (component: Locator) =>
    component.getByTestId('Filters').click();
  const pickFilters = async (page: Page) => {
    await nameFilter(page).fill('vmware1');
    await advancedFilters(page).getByTestId(labelType).click();
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText('VMWare 6/7', { exact: true })
      .click();
    await page.keyboard.press('Escape');
    await advancedFilters(page).getByTestId(labelPoller).click();
    for (const poller of ['poller1', 'poller2']) {
      await page
        .locator('.MuiAutocomplete-popper')
        .getByText(poller, { exact: true })
        .click();
    }
    await page.keyboard.press('Escape');
  };

  test('opens the advanced filters', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mountConnectors(mount);
    await expect(
      component.locator('div[data-testid="search-bar"]')
    ).toBeVisible();
    await openFilters(component);

    const filters = advancedFilters(page);
    await expect(nameFilter(page)).toBeVisible();
    for (const testId of [labelPoller, labelType, labelSearch, labelClear]) {
      await expect(filters.getByTestId(testId).first()).toBeVisible();
    }
    await expectThemedScreenshot(filters, 'additional-connectors-filters');
  });

  test('keeps the search bar and the name filter in sync', async ({
    mount,
    page
  }) => {
    const component = await mountConnectors(mount);
    await searchBar(component).fill('vmware1');
    await openFilters(component);
    await expect(nameFilter(page)).toHaveValue('vmware1');

    await nameFilter(page).fill('vmware2');
    await expect(searchBar(component)).toHaveValue('vmware2');
  });

  test('searches with the search bar content', async ({ mount }) => {
    const component = await mountConnectors(mount);
    await searchBar(component).fill('vmware1');

    await expect
      .poll(lastSearch)
      .toEqual({ $and: [{ $or: [{ name: { $rg: 'vmware1' } }] }] });
  });

  test('searches with the selected filters', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openFilters(component);
    await pickFilters(page);
    await advancedFilters(page).getByTestId(labelSearch).click();

    await expect.poll(lastSearch).toEqual({
      $and: [
        { $or: [{ name: { $rg: 'vmware1' } }] },
        { $or: [{ type: { $in: ['vmware_v6'] } }] },
        { $or: [{ 'poller.id': { $in: [1, 2] } }] }
      ]
    });
  });

  test('clears the filters and the search bar', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openFilters(component);
    await pickFilters(page);
    await advancedFilters(page).getByTestId(labelClear).click();

    await expect(searchBar(component)).toHaveValue('');
    await expect(nameFilter(page)).toHaveValue('');
    await expect(advancedFilters(page).locator('.MuiChip-root')).toHaveCount(0);
    await expect.poll(lastSearch).toEqual({ $and: [] });
  });
});

test.describe('modal', () => {
  const modal = (page: Page) => page.getByRole('dialog');
  const nameField = (page: Page) =>
    modal(page).locator(`input[data-testid="${labelName}"]`);
  const parameter = (page: Page, key: string) =>
    modal(page).locator(`input[data-testid="${key}_value"]`);
  const port = (page: Page) => modal(page).locator('input[name="port"]');
  const submit = (page: Page) => modal(page).getByTestId('submit');
  const blurField = (page: Page) =>
    modal(page).getByTestId('Modal-header').click();
  const openCreation = async (component: Locator, page: Page) => {
    await component.getByTestId('add-resource').click();
    await expect(
      modal(page).getByText(labelAddAnAdditionalConfiguration)
    ).toBeVisible();
  };
  const openEdition = async (component: Locator, page: Page) => {
    await component.getByText('VMWare1', { exact: true }).click();
    await expect(
      modal(page).getByText(labelModifyConnectorConfiguration)
    ).toBeVisible();
    await expect(nameField(page)).toHaveValue('VMWare1');
  };
  const selectPoller = async (page: Page) => {
    await modal(page).getByTestId(labelSelectPollers).click();
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText('poller1', { exact: true })
      .click();
    await page.keyboard.press('Escape');
  };
  const fillMandatoryFields = async (page: Page) => {
    await nameField(page).fill('New name');
    await selectPoller(page);
    await parameter(page, ParameterKeys.url).fill('http://10.10.10.10/sdk');
    await parameter(page, ParameterKeys.username).fill('username');
    await parameter(page, ParameterKeys.password).fill('password');
  };
  const expectError = async (page: Page, error: string, visible = true) => {
    if (visible) {
      await expect(modal(page).getByText(error).first()).toBeVisible();

      return;
    }
    await expect(modal(page).getByText(error)).toHaveCount(0);
  };

  test('opens an empty creation form', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openCreation(component, page);

    await expect(nameField(page)).toHaveValue('');
    await expect(modal(page).getByLabel(labelDescription)).toHaveValue('');
    await expect(
      modal(page).getByTestId(labelType).locator('..')
    ).toContainText('VMWare 6/7');
    await expect(modal(page).getByTestId(labelSelectPollers)).toBeVisible();
    await expect(modal(page).getByText(labelvCenterESX).first()).toBeVisible();
    await expect(modal(page).getByTestId('parameterGroup')).toHaveCount(1);
    for (const [key, value] of [
      [ParameterKeys.name, 'my_vcenter'],
      [ParameterKeys.url, 'https://<ip_hostname>/sdk'],
      [ParameterKeys.username, ''],
      [ParameterKeys.password, '']
    ]) {
      await expect(parameter(page, key)).toHaveValue(value);
      await expect(parameter(page, key)).toBeEnabled();
    }
    await expect(modal(page).getByText(labelAddvCenterESX)).toBeEnabled();
    await expect(port(page)).toHaveValue('5700');
    await expect(modal(page).getByTestId('cancel')).toHaveText(labelCancel);
    await expect(submit(page)).toHaveText(labelSave);
    await expect(submit(page)).toBeDisabled();
    // A single parameter group cannot be removed.
    await expect(modal(page).getByTestId(labelRemoveVCenterESX)).toHaveCount(0);
  });

  test('opens the edition form with the connector values', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mountConnectors(mount);
    await openEdition(component, page);

    await expect(modal(page).getByLabel(labelDescription)).toHaveValue(
      'Description for VMWare1'
    );
    for (const poller of ['Poller1', 'Poller2']) {
      await expect(
        modal(page).getByText(poller, { exact: true })
      ).toBeVisible();
    }
    await expect(modal(page).getByTestId('parameterGroup')).toHaveCount(2);
    const vcenters = [
      ['vCenter1', 'https://vcenter1.example.com/sdk', 'user1'],
      ['vCenter2', '192.0.0.1', 'user2']
    ];
    for (const [index, [name, url, username]] of vcenters.entries()) {
      await expect(parameter(page, ParameterKeys.name).nth(index)).toHaveValue(
        name
      );
      await expect(parameter(page, ParameterKeys.url).nth(index)).toHaveValue(
        url
      );
      await expect(
        parameter(page, ParameterKeys.username).nth(index)
      ).toHaveValue(username);
      const password = parameter(page, ParameterKeys.password).nth(index);
      await expect(password).toHaveValue(maskedPassword);
      await expect(password).toBeDisabled();
    }
    await expect(port(page)).toHaveValue('443');
    await expect(submit(page)).toBeDisabled();

    await expectThemedScreenshot(modal(page), 'additional-connector-edition');
  });

  test('disables Save while the form has errors', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openEdition(component, page);
    await nameField(page).clear();

    await expect(submit(page)).toBeDisabled();
  });

  test('enables Save once the mandatory fields are filled', async ({
    mount,
    page
  }) => {
    const component = await mountConnectors(mount);
    await openCreation(component, page);
    await expect(submit(page)).toBeDisabled();
    await fillMandatoryFields(page);

    await expect(submit(page)).toBeEnabled();
  });

  test('adds and removes vCenter parameter groups', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openCreation(component, page);
    const groups = modal(page).getByTestId('parameterGroup');
    await expect(groups).toHaveCount(1);

    await modal(page).getByText(labelAddvCenterESX).click();
    await expect(groups).toHaveCount(2);
    const remove = modal(page).getByTestId(labelRemoveVCenterESX);
    await expect(remove).toHaveCount(2);

    await remove.first().click();
    await expect(groups).toHaveCount(1);
  });

  test.describe('validation', () => {
    for (const [name, field] of [
      ['the name', (page: Page) => nameField(page)],
      ['the vCenter name', (page: Page) => parameter(page, ParameterKeys.name)],
      ['the vCenter URL', (page: Page) => parameter(page, ParameterKeys.url)],
      [
        'the vCenter username',
        (page: Page) => parameter(page, ParameterKeys.username)
      ],
      [
        'the vCenter password',
        (page: Page) => parameter(page, ParameterKeys.password)
      ]
    ] as const) {
      test(`requires ${name}`, async ({ mount, page }) => {
        const component = await mountConnectors(mount);
        await openCreation(component, page);
        await field(page).clear();
        await blurField(page);

        await expectError(page, labelRequired);
      });
    }

    test('requires the vCenter username in edition mode', async ({
      mount,
      page
    }) => {
      const component = await mountConnectors(mount);
      await openEdition(component, page);
      await parameter(page, ParameterKeys.username).first().clear();
      await blurField(page);

      await expectError(page, labelRequired);
    });

    test('keeps the vCenter password disabled and masked in edition mode', async ({
      mount,
      page
    }) => {
      const component = await mountConnectors(mount);
      await openEdition(component, page);
      const password = parameter(page, ParameterKeys.password).first();

      await expect(password).toBeDisabled();
      await expect(password).toHaveValue(maskedPassword);
      await blurField(page);
      await expectError(page, labelRequired, false);
    });

    test('requires at least one poller', async ({ mount, page }) => {
      const component = await mountConnectors(mount);
      await openCreation(component, page);
      await selectPoller(page);
      await blurField(page);
      await modal(page).getByTestId('CancelIcon').click();

      await expectError(page, labelAteastOnePollerIsRequired);
    });

    test('requires unique vCenter names', async ({ mount, page }) => {
      const component = await mountConnectors(mount);
      await openCreation(component, page);
      await modal(page).getByText(labelAddvCenterESX).click();
      for (const index of [0, 1]) {
        await parameter(page, ParameterKeys.name).nth(index).fill('vcenter1');
      }
      await blurField(page);

      await expectError(page, labelVcenterNameMustBeUnique);
    });

    test('requires a name of at least 3 characters', async ({
      mount,
      page
    }) => {
      const component = await mountConnectors(mount);
      await openCreation(component, page);
      await nameField(page).fill('ab');
      await blurField(page);

      await expectError(page, labelNameMustBeAtLeast);
    });

    test('does not require the description', async ({ mount, page }) => {
      const component = await mountConnectors(mount);
      await openCreation(component, page);
      await modal(page).getByLabel(labelDescription).clear();
      await blurField(page);

      await expectError(page, labelRequired, false);
    });

    for (const value of ['', '0.1', '70000']) {
      test(`refuses the port "${value}"`, async ({ mount, page }) => {
        const component = await mountConnectors(mount);
        await openCreation(component, page);
        await port(page).clear();
        await port(page).pressSequentially(value);
        await blurField(page);

        await expectError(page, labelInvalidPortNumber);
      });
    }

    test('requires a valid URL or IP address for the vCenter', async ({
      mount,
      page
    }) => {
      const component = await mountConnectors(mount);
      await openCreation(component, page);
      const url = parameter(page, ParameterKeys.url);

      for (const value of ['abc', '170.600.12', 'http://exa_mple.com']) {
        await url.fill(value);
        await blurField(page);
        await expectError(page, labelMustBeAvalidURL);
      }
      for (const value of [
        '192.110.0.1/sdk',
        '170.12.12.1',
        'http://example.com'
      ]) {
        await url.fill(value);
        await blurField(page);
        await expectError(page, labelMustBeAvalidURL, false);
      }
    });
  });

  test('creates a connector', async ({ mount, page }) => {
    const component = await mountConnectors(mount);
    await openCreation(component, page);
    await fillMandatoryFields(page);
    await submit(page).click();

    await expect(page.getByText(labelAdditionalConnectorCreated)).toBeVisible();
    expect(creation.bodies).toEqual([
      {
        description: null,
        name: 'New name',
        parameters: {
          port: 5700,
          vcenters: [
            {
              id: null,
              name: 'my_vcenter',
              password: 'password',
              url: 'http://10.10.10.10/sdk',
              username: 'username'
            }
          ]
        },
        pollers: [1],
        type: 'vmware_v6'
      }
    ]);
  });

  test('updates a connector, with a new password for one vCenter', async ({
    mount,
    page
  }) => {
    const component = await mountConnectors(mount);
    await openEdition(component, page);
    await nameField(page).fill('Updated name');
    await port(page).clear();
    await port(page).pressSequentially('100');
    const password = parameter(page, ParameterKeys.password).first();
    await expect(password).toBeDisabled();
    await modal(page).getByTestId('EditIcon').first().click();
    await password.fill('new password');
    await modal(page).getByTestId('CheckIcon').click();
    await expect(modal(page).getByTestId('RestartAltIcon')).toBeVisible();
    await submit(page).click();

    await expect(page.getByText(labelAdditionalConnectorUpdated)).toBeVisible();
    expect(update.bodies).toEqual([
      {
        description: 'Description for VMWare1',
        name: 'Updated name',
        parameters: {
          // The value typed; the Cypress spec expected 1000, an artefact of its
          // typing into the number field.
          port: 100,
          vcenters: [
            {
              id: 1,
              name: 'vCenter1',
              password: 'new password',
              url: 'https://vcenter1.example.com/sdk',
              username: 'user1'
            },
            {
              id: 2,
              // Untouched passwords are not sent back.
              name: 'vCenter2',
              password: null,
              url: '192.0.0.1',
              username: 'user2'
            }
          ]
        },
        pollers: [101, 102],
        type: 'vmware_v6'
      }
    ]);
  });

  test.describe('closing a modified form', () => {
    test('asks before leaving a form with errors', async ({ mount, page }) => {
      const component = await mountConnectors(mount);
      await openEdition(component, page);
      const vcenterName = parameter(page, ParameterKeys.name).first();
      await vcenterName.clear();
      await vcenterName.blur();
      await modal(page).getByText(labelCancel, { exact: true }).click();

      await expect(
        page.getByText('Do you want to leave this page?')
      ).toBeVisible();
    });

    test('asks to save a valid modified form', async ({ mount, page }) => {
      const component = await mountConnectors(mount);
      await openEdition(component, page);
      await nameField(page).fill('VMWare1 New name');
      await modal(page).getByText(labelCancel, { exact: true }).click();

      await expect(
        page.getByText('Do you want to save the changes?')
      ).toBeVisible();
    });
  });
});
