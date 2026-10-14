import type { Page } from '@playwright/test';

import {
  untouchedCheckOptionsPayload,
  untouchedDataProcessingPayload,
  untouchedExtendedInformationsPayload,
  untouchedNotificationsPayload,
  untouchedSchedulingOptionsPayload
} from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import {
  labelChildHosts,
  labelDefault,
  labelDown,
  labelDowntimeScheduled,
  labelFlapping,
  labelHostCategories,
  labelHostGroups,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelNo,
  labelNone,
  labelParentHosts,
  labelRecovery,
  labelUnreachable,
  labelYes
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
import {
  fillMandatoryFields,
  hostField,
  openCreation,
  openHost,
  pickOptions,
  saveButton,
  setUpHosts
} from './hostsApi';

// Migrated from Configuration/Hosts/Specs/Hosts.Form (host groups, relations
// and notifications).
test.use({ viewport: { height: 900, width: 1440 } });

// What a creation from the mandatory fields alone sends.
const minimalPayload = {
  address: '10.0.0.42',
  alias: null,
  category_ids: [],
  check_options: untouchedCheckOptionsPayload,
  child_host_ids: [],
  create_services_linked_to_templates: true,
  data_processing: untouchedDataProcessingPayload,
  extended_informations: untouchedExtendedInformationsPayload,
  host_group_ids: [],
  name: 'srv-apache-02',
  notifications: untouchedNotificationsPayload,
  parent_host_ids: [],
  poller_id: 2,
  scheduling_options: untouchedSchedulingOptionsPayload,
  severity_id: null,
  snmp_version: null,
  template_ids: [],
  timezone_id: null
};

// Found by label, so two swapped labels turn the assertion red.
const chips = (page: Page, label: string) =>
  page
    .locator('.MuiAutocomplete-root')
    .filter({ has: page.getByLabel(label, { exact: true }) })
    .locator('.MuiChip-root');
const toggle = (page: Page, testId: string, name: string) =>
  page.getByTestId(testId).getByRole('button', { exact: true, name });
const notificationOption = (page: Page, label: string) =>
  page.getByTestId(`host-form-notifications-options-${label}`);

test.describe('creation', () => {
  test('creates a host from the three mandatory fields', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await saveButton(page).click();

    // API Platform, so the payload is snake_case and the poller is an id.
    await expect.poll(() => api.creation.bodies).toEqual([minimalPayload]);
    const { pathname } = api.creation.urls[0];
    expect(pathname).toContain('/api/configuration/hosts');
    expect(pathname).not.toContain('/api/latest');
  });

  test('leaves host groups optional on an onPrem platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);

    await expect(hostField(page, 'host-form-groups')).not.toHaveAttribute(
      'required'
    );
    await expect(saveButton(page)).toBeEnabled();
    await saveButton(page).click();
    await expect.poll(() => api.creation.bodies).toEqual([minimalPayload]);
  });

  test('requires a host group before saving on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);
    await fillMandatoryFields(page);

    // The same three fields that suffice onPrem leave cloud incomplete.
    await expect(saveButton(page)).toBeDisabled();
    await expect(hostField(page, 'host-form-groups')).toHaveAttribute(
      'required'
    );
    await pickOptions(page, 'host-form-groups', ['Linux servers']);
    await saveButton(page).click();

    await expect
      .poll(() => api.creation.bodies)
      .toEqual([
        {
          address: '10.0.0.42',
          alias: null,
          category_ids: [],
          check_options: untouchedCheckOptionsPayload,
          child_host_ids: [],
          data_processing: {
            check_freshness: 'use_default',
            event_handler_command_id: null,
            event_handler_enabled: 'use_default',
            freshness_threshold: null
          },
          // Alt icon and comments are refused on cloud.
          extended_informations: {
            action_url: null,
            geo_coordinates: null,
            icon_id: null,
            note: null,
            note_url: null
          },
          host_group_ids: [1],
          name: 'srv-apache-02',
          parent_host_ids: [],
          poller_id: 2,
          scheduling_options: {
            check_timeperiod_id: null,
            max_check_attempts: null,
            normal_check_interval: null,
            retry_check_interval: null
          },
          severity_id: null,
          snmp_version: null,
          template_ids: [],
          timezone_id: null
        }
      ]);
  });

  test('creates a host with its categories, parent and child hosts', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await pickOptions(page, 'host-form-categories', ['Physical']);
    await pickOptions(page, 'host-form-parent-hosts', ['host 1']);
    await pickOptions(page, 'host-form-child-hosts', ['host 2']);
    await saveButton(page).click();

    await expect
      .poll(() => api.creation.bodies)
      .toEqual([
        {
          ...minimalPayload,
          category_ids: [3],
          child_host_ids: [2],
          parent_host_ids: [1]
        }
      ]);
  });
});

test('opens an existing host on its relations', async ({
  mockApi,
  mount,
  page
}) => {
  const { component } = await setUpHosts({ mockApi, mount });
  await openHost(component, 'host 0');

  for (const [label, chip] of [
    [labelHostGroups, 'Linux servers'],
    [labelHostCategories, 'Virtual'],
    [labelParentHosts, 'host 1'],
    [labelChildHosts, 'host 2']
  ]) {
    await expect(chips(page, label)).toHaveText([chip]);
  }
});

test.describe('notifications', () => {
  test('opens an existing host on its notification settings', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(
      toggle(page, 'host-form-notifications-enabled', labelNo)
    ).toHaveAttribute('aria-pressed', 'true');
    await expect(chips(page, labelLinkedContacts)).toHaveText(['admin']);
    await expect(chips(page, labelLinkedContactGroups)).toHaveText([
      'Supervisors'
    ]);
    await expect(
      hostField(page, 'host-form-notifications-interval')
    ).toHaveValue('3');
    await expect(
      hostField(page, 'host-form-notifications-firstDelay')
    ).toHaveValue('1');
    // Left out of the response, so still empty.
    await expect(
      hostField(page, 'host-form-notifications-recoveryDelay')
    ).toHaveValue('');
    await expect(
      hostField(page, 'host-form-notifications-timeperiod')
    ).toHaveValue('24x7');
    for (const [label, pressed] of [
      [labelDown, 'true'],
      [labelRecovery, 'true'],
      [labelUnreachable, 'false']
    ]) {
      await expect(notificationOption(page, label)).toHaveAttribute(
        'aria-pressed',
        pressed
      );
    }
  });

  test('opens a host with no notification settings on their defaults', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    // Host 1's detail response has no `notifications` block at all.
    await openHost(component, 'host 1');

    await expect(
      toggle(page, 'host-form-notifications-enabled', labelDefault)
    ).toHaveAttribute('aria-pressed', 'true');
    await expect(
      page.getByTestId(
        'host-form-scheduling-options-activeCheckEnabled-use_default'
      )
    ).toHaveAttribute('aria-pressed', 'true');
    await hostField(page, 'host-form-address').fill('10.0.0.42');
    await saveButton(page).click();

    await expect.poll(() => api.patch[1].bodies.length).toBe(1);
    expect(api.patch[1].bodies[0]).toMatchObject({
      check_options: untouchedCheckOptionsPayload,
      data_processing: untouchedDataProcessingPayload,
      notifications: untouchedNotificationsPayload,
      scheduling_options: untouchedSchedulingOptionsPayload,
      snmp_version: null,
      template_ids: [],
      timezone_id: null
    });
  });

  test('creates a host with its notification settings', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);

    // Default is preselected, and is not No.
    await expect(
      toggle(page, 'host-form-notifications-enabled', labelDefault)
    ).toHaveAttribute('aria-pressed', 'true');
    await toggle(page, 'host-form-notifications-enabled', labelYes).click();
    await pickOptions(page, 'host-form-notifications-contacts', ['admin']);
    await pickOptions(page, 'host-form-notifications-contact-groups', [
      'Supervisors'
    ]);
    await hostField(page, 'host-form-notifications-interval').fill('5');
    await pickOptions(page, 'host-form-notifications-timeperiod', [
      'workhours'
    ]);
    for (const label of [
      labelDown,
      labelFlapping,
      labelUnreachable,
      labelDowntimeScheduled
    ]) {
      await notificationOption(page, label).click();
    }
    await hostField(page, 'host-form-notifications-firstDelay').fill('0');
    await saveButton(page).click();

    // `enabled` as the string the API takes, not a boolean. The options come
    // from the form's own selectors: only those paths are mocked.
    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toMatchObject({
      notifications: {
        contact_groups: [3],
        contacts: [1],
        enabled: 'true',
        first_delay: 0,
        interval: 5,
        options: ['down', 'flapping', 'unreachable', 'downtime_scheduled'],
        recovery_delay: null,
        timeperiod_id: 2
      }
    });
  });

  test('sends None alone once it is chosen', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await notificationOption(page, labelDown).click();
    await page.getByLabel(labelNone, { exact: true }).click();
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toMatchObject({
      notifications: { options: ['none'] }
    });
  });
});

test.describe('additive inheritance', () => {
  const contactToggle = 'host-form-notifications-contact-additive-inheritance';
  const contactGroupToggle =
    'host-form-notifications-contact-group-additive-inheritance';

  test('hides the toggles the platform does not use', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);

    await expect(hostField(page, 'host-form-name')).toBeVisible();
    await expect(page.getByTestId(contactToggle)).toHaveCount(0);
    await expect(page.getByTestId(contactGroupToggle)).toHaveCount(0);
  });

  test('sends the toggles where the platform uses them', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts(
      { mockApi, mount },
      { isAdditiveInheritanceEnabled: true }
    );
    await openCreation(component);
    await fillMandatoryFields(page);
    await page.getByTestId(contactToggle).click();
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toMatchObject({
      notifications: {
        ...untouchedNotificationsPayload,
        contact_additive_inheritance: true,
        contact_group_additive_inheritance: false
      }
    });
  });

  test('opens and saves back the inheritance a host carries', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts(
      { mockApi, mount },
      { isAdditiveInheritanceEnabled: true }
    );
    await openHost(component, 'host 0');

    await expect(
      page.getByTestId(contactToggle).locator('input')
    ).toBeChecked();
    await expect(
      page.getByTestId(contactGroupToggle).locator('input')
    ).not.toBeChecked();
    await hostField(page, 'host-form-address').fill('10.0.0.42');
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toMatchObject({
      notifications: {
        contact_additive_inheritance: true,
        contact_group_additive_inheritance: false
      }
    });
  });
});
