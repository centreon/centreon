import type { Locator, Page } from '@playwright/test';

import { untouchedCheckOptionsPayload } from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import {
  labelAddNewEntry,
  labelAlreadyExists,
  labelCommandInheritance,
  labelTemplateInheritance
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
import {
  fillMandatoryFields,
  hostField,
  openCreation,
  openHost,
  saveButton,
  setUpHosts
} from './hostsApi';

// Migrated from Configuration/Hosts/Specs/Hosts.Form (custom macros, #11931).
test.use({ viewport: { height: 900, width: 1440 } });

const templateBackground = 'rgb(255, 203, 107)';
const commandBackground = 'rgb(182, 252, 167)';
const ownBackground = 'rgba(0, 0, 0, 0)';

const macros = (page: Page) =>
  page.getByTestId('host-form-check-options-macros');
const macroInput = (page: Page, index: number, key: 'name' | 'value') =>
  page.locator(
    `input[data-testid="host-form-check-options-macros-${index}-${key}"]`
  );
const macroRow = (page: Page, index: number): Locator =>
  macroInput(page, index, 'name').locator('xpath=ancestor::li[1]');
const passwordToggle = (page: Page, index: number) =>
  page.getByTestId('host-form-check-options-macros-password').nth(index);
const addMacro = (page: Page) =>
  macros(page)
    .getByRole('button', { exact: true, name: labelAddNewEntry })
    .click();
const addTemplate = async (page: Page, index: number, name: string) => {
  await page
    .getByTestId('host-form-templates')
    .getByRole('button', { exact: true, name: labelAddNewEntry })
    .click();
  await hostField(page, `host-form-templates-${index}`).click();
  await page
    .locator('.MuiAutocomplete-popper')
    .getByText(name, { exact: true })
    .click();
};
const removeFirstTemplate = (page: Page) =>
  page
    .getByTestId('host-form-templates')
    .getByTestId('delete-row')
    .first()
    .click();
const expectNames = async (page: Page, names: Array<string>) => {
  for (const [index, name] of names.entries()) {
    await expect(macroInput(page, index, 'name')).toHaveValue(name);
  }
  await expect(macroInput(page, names.length, 'name')).toHaveCount(0);
};

test('creates a host with its custom macros, a password one masked', async ({
  mockApi,
  mount,
  page
}) => {
  const { api, component } = await setUpHosts({ mockApi, mount });
  await openCreation(component);
  await fillMandatoryFields(page);

  await addMacro(page);
  await macroInput(page, 0, 'name').fill(' snmpport ');
  await macroInput(page, 0, 'value').fill('161');
  await expect(macroInput(page, 0, 'value')).toHaveAttribute('type', 'text');
  await addMacro(page);
  await macroInput(page, 1, 'name').fill('api_token');
  await macroInput(page, 1, 'value').fill('s3cret');
  await passwordToggle(page, 1).click();
  await expect(macroInput(page, 1, 'value')).toHaveAttribute(
    'type',
    'password'
  );
  await expect(macroInput(page, 1, 'value')).toHaveValue('s3cret');
  await saveButton(page).click();

  // Named as the server stores them: trimmed, upper-cased.
  await expect.poll(() => api.creation.bodies.length).toBe(1);
  expect(api.creation.bodies[0]).toEqual(
    expect.objectContaining({
      check_options: {
        ...untouchedCheckOptionsPayload,
        macros: [
          {
            id: null,
            is_password: false,
            name: 'SNMPPORT',
            parent: null,
            value: '161'
          },
          {
            id: null,
            is_password: true,
            name: 'API_TOKEN',
            parent: null,
            value: 's3cret'
          }
        ]
      }
    })
  );
});

test('refuses two custom macros of the same name', async ({
  mockApi,
  mount,
  page
}) => {
  const { component } = await setUpHosts({ mockApi, mount });
  await openCreation(component);
  await fillMandatoryFields(page);
  await addMacro(page);
  await macroInput(page, 0, 'name').fill('port');
  await addMacro(page);
  // The same name once the server upper-cases it.
  await macroInput(page, 1, 'name').fill('PORT ');
  await macroInput(page, 1, 'name').blur();

  await expect(macros(page).getByText(labelAlreadyExists)).toBeVisible();
  await expect(saveButton(page)).toBeDisabled();
});

test.describe('existing host', () => {
  test('opens on its custom macros', async ({ mockApi, mount, page }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await expectNames(page, ['SNMPPORT', 'API_TOKEN', 'OS', 'WARNING']);
    await expect(macroInput(page, 0, 'value')).toHaveValue('161');
    // Masked and locked: the API never sends a password back.
    const password = macroInput(page, 1, 'value');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(password).toHaveValue('********');
    await expect(password).toBeDisabled();
  });

  test('tints inherited macros as legacy does, until they are changed', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(
      macros(page).getByText(labelTemplateInheritance)
    ).toBeVisible();
    await expect(macros(page).getByText(labelCommandInheritance)).toBeVisible();
    await expect(macroRow(page, 0)).toHaveCSS(
      'background-color',
      ownBackground
    );
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      templateBackground
    );
    await expect(macroRow(page, 3)).toHaveCSS(
      'background-color',
      commandBackground
    );

    // Overridden: the host's own from now on.
    await macroInput(page, 2, 'value').fill('debian');
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      ownBackground
    );
    // Back to the inherited value: inherited again.
    await macroInput(page, 2, 'value').fill('linux');
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      templateBackground
    );
  });

  test('overrides an inherited macro and replaces a stored password', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await page
      .getByTestId('host-form-check-options-macros-1-value-clear')
      .click();
    await macroInput(page, 1, 'value').fill('n3w');
    await macroInput(page, 2, 'value').fill('debian');
    await saveButton(page).click();

    // Id and parent tell the server which inherited macro this overrides.
    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    const body = api.patch[0].bodies[0] as {
      check_options: { macros: Array<unknown> };
    };
    expect(body.check_options.macros.slice(1)).toEqual([
      {
        id: 2,
        is_password: true,
        name: 'API_TOKEN',
        parent: null,
        value: 'n3w'
      },
      {
        id: 11,
        is_password: false,
        name: 'OS',
        parent: 'template',
        value: 'debian'
      }
    ]);
  });

  test('keeps a stored password one, replaced only once cleared', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    // As legacy: a stored password stays one; other macros may change.
    await expect(passwordToggle(page, 1)).toBeDisabled();
    await expect(passwordToggle(page, 0)).toBeEnabled();
    const password = macroInput(page, 1, 'value');
    await page
      .getByTestId('host-form-check-options-macros-1-value-clear')
      .click();
    await expect(password).toBeEnabled();
    await expect(password).toHaveValue('');
    await expect(password).toBeFocused();
    // Back to keeping the stored one.
    await page
      .getByTestId('host-form-check-options-macros-1-value-revert')
      .click();
    await expect(password).toBeDisabled();
    await expect(password).toHaveValue('********');
    // Cleared and left empty: an empty password.
    await page
      .getByTestId('host-form-check-options-macros-1-value-clear')
      .click();
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    const body = api.patch[0].bodies[0] as {
      check_options: { macros: Array<unknown> };
    };
    expect(body.check_options.macros[1]).toEqual({
      id: 2,
      is_password: true,
      name: 'API_TOKEN',
      parent: null,
      value: ''
    });
  });

  test('lets a macro added on edition turn into a password and back', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(macroInput(page, 3, 'name')).toHaveValue('WARNING');
    await addMacro(page);

    await passwordToggle(page, 4).click();
    await expect(macroInput(page, 4, 'value')).toHaveAttribute(
      'type',
      'password'
    );
    await passwordToggle(page, 4).click();
    await expect(macroInput(page, 4, 'value')).toHaveAttribute('type', 'text');
  });

  test('deletes a custom macro by leaving it out', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(macroInput(page, 0, 'name')).toHaveValue('SNMPPORT');
    await macros(page).getByTestId('delete-row').first().click();
    await saveButton(page).click();

    // The stored password keeps its own id and null value.
    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    const body = api.patch[0].bodies[0] as {
      check_options: { macros: Array<unknown> };
    };
    expect(body.check_options.macros).toEqual([
      { id: 2, is_password: true, name: 'API_TOKEN', parent: null, value: null }
    ]);
  });
});

test.describe('inherited from the templates picked', () => {
  test('shows what each template lends, the first one prevailing', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);

    await addTemplate(page, 0, 'generic-active-host');
    await expectNames(page, ['OS', 'TIMEOUT', 'WARNING']);
    expect(
      api.selectors['configuration/hosts/host_templates/5'].urls[0].pathname
    ).toContain('/api/configuration/hosts/host_templates/5');
    await expect(macroInput(page, 0, 'value')).toHaveValue('linux');
    await expect(macroRow(page, 0)).toHaveCSS(
      'background-color',
      templateBackground
    );
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      commandBackground
    );

    // Template 5 still gives OS; template 6's WARNING beats the command's.
    await addTemplate(page, 1, 'generic-passive-host');
    await expect(macroInput(page, 2, 'value')).toHaveValue('80');
    await expectNames(page, ['OS', 'TIMEOUT', 'WARNING']);
    await expect(macroInput(page, 0, 'value')).toHaveValue('linux');
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      templateBackground
    );

    // Only template 6 left.
    await removeFirstTemplate(page);
    await expectNames(page, ['WARNING', 'OS']);
    await expect(macroInput(page, 1, 'value')).toHaveValue('windows');
  });

  test("leaves the templates' command macros out once a check command is picked", async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await hostField(page, 'host-form-check-options-command').click();
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText('check-host-alive', { exact: true })
      .click();
    await addTemplate(page, 0, 'generic-active-host');

    await expectNames(page, ['OS', 'TIMEOUT']);
  });

  test('lends nothing, silently, while the endpoint answers 404', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await addTemplate(page, 0, 'linux-server-standard');

    await expect
      .poll(() => api.selectors['configuration/hosts/host_templates/7'].urls)
      .toHaveLength(1);
    await expect(macroInput(page, 0, 'name')).toHaveCount(0);
    await expect(page.getByText('Host template not found')).toHaveCount(0);
  });

  test('sends only the macros the host changes or owns', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await addTemplate(page, 0, 'generic-active-host');
    await expect(macroInput(page, 1, 'name')).toHaveValue('TIMEOUT');
    await macroInput(page, 1, 'value').fill('20');
    await expect(macroRow(page, 1)).toHaveCSS(
      'background-color',
      ownBackground
    );
    await saveButton(page).click();

    // Id and parent make TIMEOUT an override of template 5's.
    await expect.poll(() => api.creation.bodies.length).toBe(1);
    const body = api.creation.bodies[0] as {
      check_options: { macros: Array<unknown> };
    };
    expect(body.check_options.macros).toEqual([
      {
        id: 31,
        is_password: false,
        name: 'TIMEOUT',
        parent: 'template',
        value: '20'
      }
    ]);
  });

  test('keeps the rows as they are when a template fails to load', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    // Registered last, so it wins over the template 5 mock.
    await mockApi({
      endpoint: 'configuration/hosts/host_templates/5',
      response: { code: 500, message: 'Internal error' },
      status: 500
    });
    await openHost(component, 'host 0');
    await expect(macroInput(page, 2, 'name')).toHaveValue('OS');
    await removeFirstTemplate(page);

    await expect(page.getByText('Internal error')).toBeVisible();
    await expect(macroInput(page, 2, 'name')).toHaveValue('OS');
    await expect(macroInput(page, 2, 'value')).toHaveValue('linux');
    await expect(macroInput(page, 3, 'name')).toHaveValue('WARNING');
  });

  test('keeps the rows as they are, silently, when a template is not found', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(macroInput(page, 2, 'name')).toHaveValue('OS');
    // Host 0 has templates 6 and 5; template 7 answers 404.
    await addTemplate(page, 2, 'linux-server-standard');

    await expect
      .poll(() => api.selectors['configuration/hosts/host_templates/7'].urls)
      .toHaveLength(1);
    await expect(macroInput(page, 2, 'name')).toHaveValue('OS');
    await expect(macroInput(page, 3, 'name')).toHaveValue('WARNING');
    await expect(macroRow(page, 2)).toHaveCSS(
      'background-color',
      templateBackground
    );
    await expect(page.getByText('Host template not found')).toHaveCount(0);
  });

  test('follows the templates changed on an existing host', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    // As loaded: nothing fetched, the detail's inherited macros shown.
    await expectNames(page, ['SNMPPORT', 'API_TOKEN', 'OS', 'WARNING']);
    expect(
      api.selectors['configuration/hosts/host_templates/5'].urls
    ).toHaveLength(0);

    // Host 0's templates are 6 then 5: keep 5 alone. Host 0 keeps its own
    // check command's WARNING, not template 5's command macros; template 5's
    // OS replaces template 6's.
    await removeFirstTemplate(page);
    await expectNames(page, [
      'SNMPPORT',
      'API_TOKEN',
      'WARNING',
      'OS',
      'TIMEOUT'
    ]);
    await expect(macroInput(page, 3, 'value')).toHaveValue('linux');
  });
});
