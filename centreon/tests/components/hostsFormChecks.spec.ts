import type { Page } from '@playwright/test';

import { untouchedSchedulingOptionsPayload } from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import {
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelNone,
  labelSnmpVersion,
  labelTimezone
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
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

// Migrated from Configuration/Hosts/Specs/Hosts.Form (SNMP, timezone,
// scheduling, check command and data processing).
test.use({ viewport: { height: 900, width: 1440 } });

const snmpVersion = (page: Page) =>
  page.getByLabel(labelSnmpVersion, { exact: true });
const pressed = (page: Page, testId: string) =>
  expect(page.getByTestId(testId)).toHaveAttribute('aria-pressed', 'true');
const scheduling = (field: string) => `host-form-scheduling-options-${field}`;
const dataProcessing = (field: string) => `host-form-data-processing-${field}`;

test.describe('SNMP, timezone and scheduling', () => {
  test('opens an existing host on its settings', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    const community = hostField(page, 'host-form-snmp-community');
    await expect(community).toHaveValue('');
    await expect(community).toHaveAttribute('type', 'password');
    await expect(snmpVersion(page)).toHaveValue('2c');
    await expect(hostField(page, 'host-form-timezone')).toHaveValue(
      'Europe/Paris'
    );
    for (const [field, value] of [
      ['maxCheckAttempts', '3'],
      ['normalCheckInterval', '5'],
      ['retryCheckInterval', '']
    ]) {
      await expect(hostField(page, scheduling(field))).toHaveValue(value);
    }
    await pressed(page, scheduling('activeCheckEnabled-false'));
    await pressed(page, scheduling('passiveCheckEnabled-true'));
  });

  test('clears them on an existing host', async ({ mockApi, mount, page }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(snmpVersion(page)).toHaveValue('2c');

    await snmpVersion(page).click();
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText(labelNone, { exact: true })
      .click();
    await expect(snmpVersion(page)).toHaveValue('');
    await clearAutocomplete(page, 'host-form-timezone');
    await clearAutocomplete(page, scheduling('checkPeriod'));
    await hostField(page, scheduling('maxCheckAttempts')).clear();
    await page
      .getByTestId(scheduling('activeCheckEnabled-use_default'))
      .click();
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({
        scheduling_options: {
          active_check_enabled: 'use_default',
          check_timeperiod_id: null,
          max_check_attempts: null,
          normal_check_interval: 5,
          passive_check_enabled: 'true',
          retry_check_interval: null
        },
        snmp_version: null,
        timezone_id: null
      })
    );
  });

  test('creates a host with them', async ({ mockApi, mount, page }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);
    await hostField(page, 'host-form-snmp-community').fill('public');
    await snmpVersion(page).click();
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText('2c', { exact: true })
      .click();
    await pickOptions(page, 'host-form-timezone', ['Europe/Paris']);
    await hostField(page, scheduling('maxCheckAttempts')).fill('3');
    await hostField(page, scheduling('retryCheckInterval')).fill('1');
    await pressed(page, scheduling('activeCheckEnabled-use_default'));
    await page.getByTestId(scheduling('activeCheckEnabled-true')).click();
    await page.getByTestId(scheduling('passiveCheckEnabled-false')).click();
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({
        scheduling_options: {
          active_check_enabled: 'true',
          check_timeperiod_id: null,
          max_check_attempts: 3,
          normal_check_interval: null,
          passive_check_enabled: 'false',
          retry_check_interval: 1
        },
        snmp_community: 'public',
        snmp_version: '2c',
        timezone_id: 7
      })
    );
    const { pathname } = api.selectors['configuration/timezones'].urls[0];
    expect(pathname).toContain('/api/configuration/timezones');
    expect(pathname).not.toContain('/api/latest');
  });

  test('refuses a check interval below 1', async ({ mockApi, mount, page }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    const interval = hostField(page, scheduling('normalCheckInterval'));
    await interval.fill('0');
    await interval.blur();

    await expect(page.getByText(labelMustBeIntegerOfAtLeastOne)).toBeVisible();
  });

  test('offers no check toggles on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    await expect(hostField(page, scheduling('maxCheckAttempts'))).toBeVisible();
    await expect(
      page.getByTestId(scheduling('activeCheckEnabled'))
    ).toHaveCount(0);
    await expect(
      page.getByTestId(scheduling('passiveCheckEnabled'))
    ).toHaveCount(0);
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

    await expect(snmpVersion(page)).toHaveValue('2c');
    for (const field of [
      hostField(page, 'host-form-snmp-community'),
      snmpVersion(page),
      page.getByLabel(labelTimezone, { exact: true }),
      hostField(page, scheduling('maxCheckAttempts')),
      page.getByTestId(scheduling('activeCheckEnabled-true'))
    ]) {
      await expect(field).toBeDisabled();
    }
  });
});

test.describe('check command', () => {
  test('opens an existing host on its command and period', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(
      hostField(page, 'host-form-check-options-command')
    ).toHaveValue('check-host-alive');
    const args = hostField(page, 'host-form-check-options-args');
    await expect(args).toHaveValue('!3!80%');
    await expect(args).toBeEnabled();
    await expect(hostField(page, scheduling('checkPeriod'))).toHaveValue(
      'workhours'
    );
  });

  test('creates a host with its command, arguments and period', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);

    // Arguments are refused without a command.
    const args = hostField(page, 'host-form-check-options-args');
    await expect(args).toBeDisabled();
    await pickOptions(page, 'host-form-check-options-command', [
      'check-host-alive'
    ]);
    await expect(args).toBeEnabled();
    await args.fill('3!80%');
    await pickOptions(page, scheduling('checkPeriod'), ['workhours']);
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({
        check_options: { args: ['3', '80%'], command_id: 9, macros: [] },
        scheduling_options: {
          ...untouchedSchedulingOptionsPayload,
          check_timeperiod_id: 2
        }
      })
    );
    // Only active check commands, as legacy offers.
    const url = api.selectors['configuration/commands'].urls[0];
    expect(url.searchParams.getAll('type[]')).toEqual(['Check']);
    expect(url.searchParams.get('is_activated')).toBe('true');
  });

  test('sends no arguments once the command is removed', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(
      hostField(page, 'host-form-check-options-command')
    ).toHaveValue('check-host-alive');

    await clearAutocomplete(page, 'host-form-check-options-command');
    await expect(
      hostField(page, 'host-form-check-options-args')
    ).toBeDisabled();
    await saveButton(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({
        check_options: expect.objectContaining({ args: [], command_id: null })
      })
    );
  });

  test('is offered on a cloud platform', async ({ mockApi, mount, page }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    for (const testId of [
      'host-form-check-options-command',
      'host-form-check-options-args',
      scheduling('checkPeriod')
    ]) {
      await expect(hostField(page, testId)).toBeVisible();
    }
  });
});

test.describe('data processing', () => {
  test('opens an existing host on its settings', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openHost(component, 'host 0');

    await pressed(page, dataProcessing('checkFreshness-true'));
    await pressed(page, dataProcessing('flapDetectionEnabled-true'));
    await pressed(page, dataProcessing('eventHandlerEnabled-false'));
    for (const [field, value] of [
      ['freshnessThreshold', '120'],
      ['acknowledgmentTimeout', '15'],
      ['lowFlapThreshold', ''],
      ['highFlapThreshold', '50'],
      ['eventHandler', 'restart-httpd'],
      ['eventHandlerArgs', '!80!graceful']
    ]) {
      await expect(hostField(page, dataProcessing(field))).toHaveValue(value);
    }
  });

  test('creates a host with its settings', async ({ mockApi, mount, page }) => {
    const { api, component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    await fillMandatoryFields(page);

    await pressed(page, dataProcessing('checkFreshness-use_default'));
    await page.getByTestId(dataProcessing('checkFreshness-true')).click();
    await hostField(page, dataProcessing('freshnessThreshold')).fill('300');
    await hostField(page, dataProcessing('acknowledgmentTimeout')).fill('10');
    await page
      .getByTestId(dataProcessing('flapDetectionEnabled-false'))
      .click();
    await hostField(page, dataProcessing('lowFlapThreshold')).fill('20');
    await hostField(page, dataProcessing('highFlapThreshold')).fill('40');
    await page.getByTestId(dataProcessing('eventHandlerEnabled-true')).click();
    await pickOptions(page, dataProcessing('eventHandler'), ['restart-httpd']);
    await hostField(page, dataProcessing('eventHandlerArgs')).fill(
      '80!!graceful'
    );
    await saveButton(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({
        data_processing: {
          acknowledgment_timeout: 10,
          check_freshness: 'true',
          event_handler_args: ['80', '', 'graceful'],
          event_handler_command_id: 7,
          event_handler_enabled: 'true',
          flap_detection_enabled: 'false',
          freshness_threshold: 300,
          high_flap_threshold: 40,
          low_flap_threshold: 20
        }
      })
    );
    const url = api.selectors['configuration/commands'].urls[0];
    expect(url.pathname).not.toContain('/api/latest');
    expect(url.searchParams.get('is_activated')).toBe('true');
  });

  test('refuses a negative flap threshold', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts({ mockApi, mount });
    await openCreation(component);
    const threshold = hostField(page, dataProcessing('highFlapThreshold'));
    await threshold.fill('-1');
    await threshold.blur();

    await expect(
      page.getByText(labelMustBePositiveIntegerOrZero)
    ).toBeVisible();
  });

  test('offers only the cloud settings on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const { component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    for (const field of [
      'checkFreshness',
      'freshnessThreshold',
      'eventHandlerEnabled',
      'eventHandler'
    ]) {
      await expect(
        page.getByTestId(dataProcessing(field)).first()
      ).toBeAttached();
    }
    for (const field of [
      'acknowledgmentTimeout',
      'flapDetectionEnabled',
      'lowFlapThreshold',
      'highFlapThreshold',
      'eventHandlerArgs'
    ]) {
      await expect(page.getByTestId(dataProcessing(field))).toHaveCount(0);
    }
  });

  test('opens and saves back the settings of a cloud host', async ({
    mockApi,
    mount,
    page
  }) => {
    const { api, component } = await setUpHosts(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openHost(component, 'host 2');

    await pressed(page, dataProcessing('checkFreshness-false'));
    await pressed(page, dataProcessing('eventHandlerEnabled-true'));
    await expect(
      hostField(page, dataProcessing('freshnessThreshold'))
    ).toHaveValue('60');
    await expect(hostField(page, dataProcessing('eventHandler'))).toHaveValue(
      ''
    );
    await hostField(page, 'host-form-address').fill('10.0.0.42');
    await saveButton(page).click();

    await expect.poll(() => api.patch[2].bodies.length).toBe(1);
    expect(api.patch[2].bodies[0]).toEqual(
      expect.objectContaining({
        data_processing: {
          check_freshness: 'false',
          event_handler_command_id: null,
          event_handler_enabled: 'true',
          freshness_threshold: 60
        }
      })
    );
  });
});
