import type { Page } from '@playwright/test';

import {
  labelAddNewEntry,
  labelCreateServicesLinkedToTemplates,
  labelInvalidGeographicCoordinates
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, moveSortable, test } from './fixtures';
import {
  clearAutocomplete,
  fillMandatoryFields,
  hostField,
  openCreation,
  openHost,
  pickOptions,
  saveButton,
  setUpHosts
} from './hostsApi';

// Migrated from Configuration/Hosts/Specs/Hosts.Form (extended infos, severity
// and templates).
test.use({ viewport: { height: 900, width: 1440 } });

const extended = (field: string) => `host-form-extended-infos-${field}`;
const iconPreview = (page: Page) =>
  page.getByTestId(extended('icon-preview')).locator('img');
const servicesToggle = (page: Page) =>
  page.getByLabel(labelCreateServicesLinkedToTemplates, { exact: true });
const templates = (page: Page) => page.getByTestId('host-form-templates');
// The macros list has its own "Add new entry" button.
const addTemplateButton = (page: Page) =>
  templates(page).getByRole('button', { exact: true, name: labelAddNewEntry });
const addTemplateRow = (page: Page) => addTemplateButton(page).click();

test.describe('extended infos and severity', () => {
  test('opens an existing host on them', async ({ mockApi, mount, page }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    for (const [field, value] of [
      ['note', 'Front web server'],
      // Left out of the response, so still empty.
      ['noteUrl', ''],
      ['actionUrl', 'https://example.com/actions/host-0'],
      ['geoCoordinates', '48.8566,2.3522'],
      ['altIcon', ''],
      ['comment', 'Racked in room B'],
      ['icon', 'server.png']
    ]) {
      await expect(hostField(page, extended(field))).toHaveValue(value);
    }
    await expect(iconPreview(page)).toHaveAttribute('alt', 'server.png');
    await expect(hostField(page, 'host-form-severity')).toHaveValue('Minor');
  });

  test('opens a host without them on empty fields', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 1');

    await expect(hostField(page, 'host-form-name')).toHaveValue('host 1');
    await expect(hostField(page, extended('note'))).toHaveValue('');
    await expect(hostField(page, extended('icon'))).toHaveValue('');
    await expect(iconPreview(page)).toHaveCount(0);
    await expect(hostField(page, 'host-form-severity')).toHaveValue('');
  });

  test('clears them on an existing host', async ({ mockApi, mount, page }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(hostField(page, extended('note'))).toHaveValue(
      'Front web server'
    );

    await hostField(page, extended('note')).clear();
    await hostField(page, extended('actionUrl')).fill('   ');
    await clearAutocomplete(page, extended('icon'));
    await expect(iconPreview(page)).toHaveCount(0);
    await clearAutocomplete(page, 'host-form-severity');
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({
        extended_informations: expect.objectContaining({
          action_url: null,
          icon_id: null,
          note: null
        }),
        severity_id: null
      })
    );
  });

  test('cannot be changed by a read-only user', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { hasWriteAccess: false }
    );
    await openHost(component, 'host 0');

    for (const testId of [
      extended('note'),
      extended('comment'),
      extended('icon'),
      'host-form-severity'
    ]) {
      await expect(hostField(page, testId)).toBeDisabled();
    }
  });

  test('creates a host with them', async ({ mockApi, mount, page }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    for (const [field, value] of [
      ['note', '  Front web server  '],
      ['noteUrl', 'https://example.com/notes'],
      ['actionUrl', 'https://example.com/actions'],
      ['geoCoordinates', '-33.8688,151.2093'],
      ['altIcon', 'Web server'],
      ['comment', 'Racked in room B']
    ]) {
      await hostField(page, extended(field)).fill(value);
    }
    await pickOptions(page, extended('icon'), ['router.png']);
    await expect(iconPreview(page)).toHaveAttribute('alt', 'router.png');
    await pickOptions(page, 'host-form-severity', ['Critical']);
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({
        extended_informations: {
          action_url: 'https://example.com/actions',
          alt_icon: 'Web server',
          comment: 'Racked in room B',
          geo_coordinates: '-33.8688,151.2093',
          icon_id: 13,
          note: 'Front web server',
          note_url: 'https://example.com/notes'
        },
        // A field of the host, not of its extended informations.
        severity_id: 1
      })
    );
    // The host-scoped selectors, granted by host write access.
    expect(api.selectors['configuration/hosts/medias'].urls).not.toHaveLength(
      0
    );
    expect(
      api.selectors['configuration/hosts/host_severities'].urls
    ).not.toHaveLength(0);
  });

  test('refuses geographic coordinates out of range', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    const coordinates = hostField(page, extended('geoCoordinates'));
    await coordinates.fill('91,2');
    await coordinates.blur();

    await expect(
      page.getByText(labelInvalidGeographicCoordinates)
    ).toBeVisible();
  });

  test('offers no alt icon nor comments on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    for (const testId of [
      ...['note', 'noteUrl', 'actionUrl', 'geoCoordinates', 'icon'].map(
        extended
      ),
      'host-form-severity'
    ]) {
      await expect(hostField(page, testId)).toBeAttached();
    }
    for (const field of ['altIcon', 'comment']) {
      await expect(page.getByTestId(extended(field))).toHaveCount(0);
    }
  });
});

test.describe('templates', () => {
  test('creates a host from its templates, in the order they were added', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);

    await addTemplateRow(page);
    await pickOptions(page, 'host-form-templates-0', ['linux-server-standard']);
    await addTemplateRow(page);
    await hostField(page, 'host-form-templates-1').click();
    // Not offered twice.
    const popper = page.locator('.MuiAutocomplete-popper');
    await expect(
      popper.getByRole('option').filter({ hasText: 'linux-server-standard' })
    ).toHaveAttribute('aria-disabled', 'true');
    await popper.getByText('generic-active-host', { exact: true }).click();
    // On by default when creating, as legacy does.
    await expect(servicesToggle(page)).toBeChecked();
    await servicesToggle(page).click();
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({
        create_services_linked_to_templates: false,
        template_ids: [7, 5]
      })
    );
    const { pathname } =
      api.selectors['configuration/hosts/host_templates'].urls[0];
    expect(pathname).toContain('/api/configuration/hosts/host_templates');
    expect(pathname).not.toContain('/api/latest');
  });

  test('searches the templates by the name typed', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await addTemplateRow(page);
    await pickOptions(page, 'host-form-templates-0', ['linux-server-standard']);

    // A row with a value excludes it first: the typed text is not the first
    // search condition.
    const row = hostField(page, 'host-form-templates-0');
    await row.clear();
    await row.pressSequentially('g');

    await expect
      .poll(() =>
        api.selectors['configuration/hosts/host_templates'].urls.map((url) =>
          url.searchParams.get('name[lk]')
        )
      )
      .toContain('g');
  });

  test('leaves out a template row left empty', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await addTemplateRow(page);
    await pickOptions(page, 'host-form-templates-0', ['linux-server-standard']);
    await addTemplateRow(page);

    // Nothing picked yet: no template page to open.
    await expect(
      page.getByTestId('host-form-templates-edit').nth(1)
    ).toBeDisabled();
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({ template_ids: [7] })
    );
  });

  test('templates and macros cannot be changed by a read-only user', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { hasWriteAccess: false }
    );
    await openHost(component, 'host 0');

    await expect(hostField(page, 'host-form-templates-0')).toBeDisabled();
    await expect(addTemplateButton(page)).toBeDisabled();
    await expect(
      page
        .getByTestId('host-form-check-options-macros')
        .getByRole('button', { exact: true, name: labelAddNewEntry })
    ).toBeDisabled();
    await expect(
      page.locator(
        'input[data-testid="host-form-check-options-macros-0-value"]'
      )
    ).toBeDisabled();
    await expect(
      templates(page).getByTestId('delete-row').first()
    ).toBeDisabled();
    await expect(servicesToggle(page)).toBeDisabled();
  });

  test('opens an existing host on its templates, in order', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(hostField(page, 'host-form-templates-0')).toHaveValue(
      'generic-passive-host'
    );
    await expect(hostField(page, 'host-form-templates-1')).toHaveValue(
      'generic-active-host'
    );
    // Never read back: off, as legacy opens an existing host.
    await expect(servicesToggle(page)).not.toBeChecked();
  });

  test('opens the configuration page of a template from its row', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(hostField(page, 'host-form-templates-0')).toHaveValue(
      'generic-passive-host'
    );
    await page.evaluate(() => {
      window.open = (...args) => {
        (window as unknown as { opened: unknown }).opened = args;

        return null;
      };
    });
    await page.getByTestId('host-form-templates-edit').first().click();

    const opened = await page.evaluate(
      () => (window as unknown as { opened: Array<string> }).opened
    );
    expect(opened[0]).toMatch(/main\.php\?p=60103&o=c&host_id=6$/);
    expect(opened[1]).toBe('_blank');
  });

  test('saves the templates in the order they were dragged to', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    const handles = templates(page).getByTestId('drag-handle');
    await expect(handles).toHaveCount(2);

    await moveSortable(page, handles.first(), 'ArrowDown');
    await expect(hostField(page, 'host-form-templates-0')).toHaveValue(
      'generic-active-host'
    );
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({ template_ids: [5, 6] })
    );
  });

  test('saves the templates left once one is removed', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(hostField(page, 'host-form-templates-0')).toHaveValue(
      'generic-passive-host'
    );
    await templates(page).getByTestId('delete-row').first().click();
    await saveButton(page).click();

    // The first row, generic-passive-host, is the one removed.
    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({ template_ids: [5] })
    );
  });

  test('are offered without the services toggle on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    await expect(addTemplateButton(page)).toBeVisible();
    await expect(servicesToggle(page)).toHaveCount(0);
  });
});
