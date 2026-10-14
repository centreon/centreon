import type { Locator, Page } from '@playwright/test';

import commandDetails from '../../cypress/fixtures/Commands/commandDetails.json';
import listCommands from '../../cypress/fixtures/Commands/listCommands.json';
import listConnectors from '../../cypress/fixtures/Commands/listConnecters.json';
import listGlobalMacros from '../../cypress/fixtures/Commands/listGlobalMacros.json';
import listInstalledPlugins from '../../cypress/fixtures/Commands/listInstalledPlugins.json';
import listStandardMacros from '../../cypress/fixtures/Commands/listStandardMacros.json';
import {
  labelAddCommand,
  labelClear,
  labelCommandLine,
  labelComments,
  labelDiscovery,
  labelEnabled,
  labelModifyCommand,
  labelName,
  labelNotification,
  labelRequired,
  labelSave,
  labelSearch,
  labelSelectOptimizationConnector,
  labelStatus,
  labelType
} from '../../www/front_src/src/Configuration/Commands/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Configuration/Commands/Specs (Listing, Filters and Modal suites).
const storyId = 'Commands/Commands/Default';
const commandsEndpoint = 'configuration/commands';
const commandLine =
  '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$ -n -p $ARG1$ -w $ARG2$ -c $ARG3$';

let listing: { urls: Array<URL> };
let creation: { bodies: Array<unknown> };
let update: { bodies: Array<unknown> };

test.use({ viewport: { height: 900, width: 1440 } });

test.beforeEach(async ({ mockApi }) => {
  listing = await mockApi({
    endpoint: commandsEndpoint,
    response: listCommands
  });
  creation = await mockApi({
    endpoint: commandsEndpoint,
    method: 'POST',
    response: commandDetails
  });
  await mockApi({
    endpoint: `${commandsEndpoint}/1`,
    response: commandDetails
  });
  update = await mockApi({
    endpoint: `${commandsEndpoint}/1`,
    method: 'PATCH',
    response: {}
  });
  const lookups: Array<[string, unknown]> = [
    ['configuration/global-macros', listGlobalMacros],
    ['configuration/standard-macros', listStandardMacros],
    ['configuration/plugins', listInstalledPlugins],
    ['configuration/connectors', listConnectors]
  ];
  for (const [endpoint, response] of lookups) {
    await mockApi({ endpoint, response });
  }
});

// The app builds raw query strings (`name[lk]=abc`): compare decoded URLs.
const lastListingUrl = () =>
  decodeURIComponent(listing.urls.at(-1)?.href ?? '');

const mountCommands = async (mount: (id: string) => Promise<Locator>) => {
  const component = await mount(storyId);
  await expect(component.getByText('check_host_alive')).toBeVisible();

  return component;
};

test.describe('listing', () => {
  test('displays the first page', async ({ expectThemedScreenshot, mount }) => {
    const component = await mountCommands(mount);

    await expectThemedScreenshot(component, 'commands-listing');
  });

  test('changes the page size', async ({ mount, page }) => {
    const component = await mountCommands(mount);

    await component.locator('[id="Rows per page"]').click();
    await page.getByRole('option', { exact: true, name: '20' }).click();
    await expect.poll(lastListingUrl).toContain('itemsPerPage=20');
  });

  test('changes the page', async ({ mount }) => {
    const component = await mountCommands(mount);

    await component.getByLabel('Next page').click();
    await expect
      .poll(() => listing.urls.at(-1)?.searchParams.get('page'))
      .toBe('2');
  });

  test('sorts by name and type', async ({ mount }) => {
    const component = await mountCommands(mount);

    for (const [label, id] of [
      [labelName, 'name'],
      [labelType, 'type']
    ]) {
      await component.getByLabel(`Column ${label}`).click();
      await expect.poll(lastListingUrl).toContain(`sort[${id}]=desc`);
    }
  });
});

test.describe('filters', () => {
  const searchBar = (component: Locator) =>
    component.getByPlaceholder(labelSearch);
  // The advanced filters open in a portalled popover.
  const advancedFilters = (component: Locator) =>
    component.page().getByTestId('advanced-filters');
  const nameFilter = (component: Locator) =>
    advancedFilters(component).locator(`input[data-testid="${labelName}"]`);
  const openFilters = (component: Locator) =>
    component.getByTestId('Filters').click();
  const pickFilters = async (component: Locator) => {
    await nameFilter(component).fill('abc');
    for (const label of [labelNotification, labelDiscovery, labelEnabled]) {
      await advancedFilters(component)
        .getByText(label, { exact: true })
        .click();
    }
    await advancedFilters(component).getByTestId(labelSearch).click();
  };

  test('opens the advanced filters', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mountCommands(mount);
    await expect(
      component.locator('div[data-testid="search-bar"]')
    ).toBeVisible();
    await openFilters(component);

    const filters = advancedFilters(component);
    await expect(filters).toBeVisible();
    await expect(nameFilter(component)).toBeVisible();
    await expect(filters.getByText(labelStatus)).toBeVisible();
    await expect(filters.getByText(labelType)).toBeVisible();
    await expect(filters.getByTestId(labelSearch)).toBeVisible();
    await expect(filters.getByTestId(labelClear)).toBeVisible();

    await expectThemedScreenshot(filters, 'commands-advanced-filters');
  });

  test('keeps the search bar and the name filter in sync', async ({
    mount
  }) => {
    const component = await mountCommands(mount);
    await searchBar(component).fill('abc');
    await openFilters(component);
    await expect(nameFilter(component)).toHaveValue('abc');

    await nameFilter(component).fill('def');
    await expect(searchBar(component)).toHaveValue('def');
  });

  test('searches with the search bar content', async ({ mount }) => {
    const component = await mountCommands(mount);
    await searchBar(component).fill('abc');

    await expect.poll(lastListingUrl).toContain('name[lk]=abc');
  });

  test('searches with the selected filters', async ({ mount }) => {
    const component = await mountCommands(mount);
    await openFilters(component);
    await pickFilters(component);

    await expect.poll(lastListingUrl).toContain('name[lk]=abc');
    const url = lastListingUrl();
    expect(url).toContain('type[]=Notification&type[]=Discovery');
    expect(url).toContain('is_activated=true');
  });

  test('clears the filters and the search bar', async ({ mount }) => {
    const component = await mountCommands(mount);
    await openFilters(component);
    await pickFilters(component);
    await expect.poll(lastListingUrl).toContain('is_activated=true');

    await advancedFilters(component).getByTestId(labelClear).click();
    await expect(searchBar(component)).toHaveValue('');
    await expect(nameFilter(component)).toHaveValue('');
    for (const name of ['Notification', 'Discovery', 'Enabled']) {
      await expect(
        advancedFilters(component).locator(`input[name="${name}"]`)
      ).not.toBeChecked();
    }
    await expect.poll(lastListingUrl).not.toContain('is_activated=true');
    const url = lastListingUrl();
    expect(url).not.toContain('name[lk]=abc');
    expect(url).not.toContain('type[]=Notification');
  });
});

test.describe('modal', () => {
  const modal = (page: Page) => page.getByRole('dialog');
  // Fields carry their test id on both the wrapper and the input.
  const field = (page: Page, testId: string) =>
    modal(page).locator(`:is(input, textarea)[data-testid="${testId}"]`);
  const typeCheckbox = (page: Page, type: string) =>
    modal(page).locator(`input[name="${type}"]`);
  const shellSyntax = (page: Page) =>
    modal(page).getByTestId('enable-shell-syntax').locator('input');
  const submit = (page: Page) => modal(page).getByTestId('submit');

  const openCreation = async (component: Locator, page: Page) => {
    await component.getByTestId('add-resource').click();
    await expect(modal(page).getByText(labelAddCommand)).toBeVisible();
  };
  const openEdition = async (component: Locator, page: Page) => {
    await component.getByText('check_host_alive').click();
    await expect(modal(page).getByText(labelModifyCommand)).toBeVisible();
    await expect(field(page, labelName)).toHaveValue('check_host_alive');
  };

  test('opens an empty creation form', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mountCommands(mount);
    await openCreation(component, page);

    await expect(field(page, labelName)).toHaveValue('');
    for (const type of [
      'Notification',
      'Check ',
      'Discovery',
      'Miscellaneous'
    ]) {
      await expect(typeCheckbox(page, type)).not.toBeChecked();
    }
    await expect(field(page, labelCommandLine)).toHaveValue('');
    await expect(shellSyntax(page)).not.toBeChecked();
    await expect(field(page, labelSelectOptimizationConnector)).toHaveValue('');
    await expect(field(page, labelComments)).toHaveValue('');

    await expectThemedScreenshot(modal(page), 'commands-creation-form');
  });

  test('opens the edition form with the command values', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mountCommands(mount);
    await openEdition(component, page);

    await expect(typeCheckbox(page, 'Notification')).toBeChecked();
    for (const type of ['Check ', 'Discovery', 'Miscellaneous']) {
      await expect(typeCheckbox(page, type)).not.toBeChecked();
    }
    await expect(field(page, labelCommandLine)).toHaveValue(commandLine);
    await expect(shellSyntax(page)).toBeChecked();
    await expect(field(page, labelSelectOptimizationConnector)).toHaveValue(
      'Telegraf'
    );
    await expect(field(page, labelComments)).toHaveValue('some comment');
    await expect(submit(page)).toHaveText(labelSave);
    await expect(submit(page)).toBeDisabled();

    await expectThemedScreenshot(modal(page), 'commands-edition-form');
  });

  test('enables Save once the mandatory fields are filled', async ({
    mount,
    page
  }) => {
    const component = await mountCommands(mount);
    await openCreation(component, page);
    await expect(submit(page)).toBeDisabled();

    await field(page, labelName).fill('New name');
    await typeCheckbox(page, 'Notification').click();
    await field(page, labelCommandLine).fill(
      '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$'
    );
    await expect(submit(page)).toBeEnabled();
  });

  test.describe('validation', () => {
    for (const [testId, required] of [
      [labelName, true],
      [labelCommandLine, true],
      [labelComments, false]
    ] as const) {
      test(`${required ? 'requires' : 'does not require'} the ${testId.toLowerCase()}`, async ({
        mount,
        page
      }) => {
        const component = await mountCommands(mount);
        await openEdition(component, page);
        await field(page, testId).clear();
        await modal(page).getByTestId('Modal-header').click();

        if (required) {
          await expect(modal(page).getByText(labelRequired)).toBeVisible();
          await expect(submit(page)).toBeDisabled();
        } else {
          await expect(modal(page).getByText(labelRequired)).toHaveCount(0);
          await expect(submit(page)).toBeEnabled();
        }
      });
    }

    test('does not require the connector', async ({ mount, page }) => {
      const component = await mountCommands(mount);
      await openEdition(component, page);
      await field(page, labelSelectOptimizationConnector).clear();
      await modal(page).getByTestId('Modal-header').click();

      await expect(modal(page).getByText(labelRequired)).toHaveCount(0);
      await expect(submit(page)).toBeEnabled();
    });
  });

  test('creates a command', async ({ mount, page }) => {
    const component = await mountCommands(mount);
    await openCreation(component, page);

    await field(page, labelName).fill('New name');
    await typeCheckbox(page, 'Discovery').click();
    await field(page, labelCommandLine).fill(
      '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$'
    );
    await shellSyntax(page).click();
    await field(page, labelSelectOptimizationConnector).click();
    await page.getByRole('option', { name: 'Telegraf' }).click();
    await field(page, labelComments).fill('some comment');
    await submit(page).click();

    await expect(page.getByText('Command created')).toBeVisible();
    expect(creation.bodies).toEqual([
      {
        command_line: '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$',
        comment: 'some comment',
        connector: '/centreon/api/latest/configuration/connectors/4',
        is_shell_enabled: true,
        name: 'New name',
        type: 'Discovery'
      }
    ]);
  });

  test('updates a command', async ({ mount, page }) => {
    const component = await mountCommands(mount);
    await openEdition(component, page);

    await field(page, labelName).fill('Updated name');
    await typeCheckbox(page, 'Discovery').click();
    await shellSyntax(page).click();
    await submit(page).click();

    await expect(page.getByText('Command updated')).toBeVisible();
    expect(update.bodies).toEqual([
      {
        command_line: commandLine,
        comment: 'some comment',
        connector:
          '/centreon/api/latest/.well-known/genid/7b9eb72f31977bee3cf4',
        is_shell_enabled: false,
        name: 'Updated name',
        type: 'Discovery'
      }
    ]);
  });

  test.describe('closing a modified form', () => {
    for (const [name, value, question] of [
      ['with errors', '', 'Do you want to leave this page?'],
      ['without errors', 'New name', 'Do you want to save the changes?']
    ]) {
      test(`asks before leaving ${name}`, async ({ mount, page }) => {
        const component = await mountCommands(mount);
        await openEdition(component, page);
        await field(page, labelName).fill(value);
        await modal(page).getByText('Cancel', { exact: true }).click();

        await expect(page.getByText(question)).toBeVisible();
      });
    }
  });
});
