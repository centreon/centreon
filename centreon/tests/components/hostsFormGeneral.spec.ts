import type { Locator, Page } from '@playwright/test';

import { panelDataTestIds } from '../../www/front_src/src/Configuration/ConfigurationBase/Panel/dataTestIds';
import {
  refusedAddressResponse,
  resolvedAddressResponse
} from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import {
  labelDataProcessing,
  labelDown,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelHostNotFound,
  labelInvalidAddress,
  labelNameMustNotStartWithModule,
  labelNotification,
  labelRelations,
  labelResolve,
  labelYes
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
import {
  type HostsApi,
  type HostsSetup,
  hostField,
  openCreation,
  openHost,
  panelHeader,
  pollersForbiddenMessage,
  saveButton,
  setUpHosts
} from './hostsApi';

// Migrated from Configuration/Hosts/Specs/Hosts.Form (panel, general
// properties, address resolution and default monitoring server).
let api: HostsApi;

test.use({ viewport: { height: 900, width: 1440 } });

const setUp = async (
  fixtures: Parameters<typeof setUpHosts>[0],
  options?: HostsSetup
) => {
  const result = await setUpHosts(fixtures, options);
  api = result.api;

  return result.component;
};

const header = panelHeader;
const save = saveButton;
const resolve = (page: Page) => page.getByTestId('host-form-address-resolve');

test.describe('panel', () => {
  test('opens a host in a side panel rather than a modal', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await component.getByText('host 0', { exact: true }).click();

    await expect(page.getByTestId(panelDataTestIds.content)).toBeVisible();
    await expect(page.getByTestId('Modal')).toHaveCount(0);
    // Named by the listing row, with no detail endpoint on this module.
    await expect(header(page)).toHaveText('host 0');
  });

  test('opens the creation form in the same panel', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openCreation(component);

    await expect(page.getByTestId(panelDataTestIds.content)).toBeVisible();
    await expect(page.getByTestId('Modal')).toHaveCount(0);
    await expect(header(page)).toHaveText('Add a host');
    for (const section of [
      labelHostConfiguration,
      labelNotification,
      labelRelations,
      labelDataProcessing,
      labelHostExtendedInfos
    ]) {
      await expect(page.getByText(section).first()).toBeVisible();
    }
    // The panel is fixed-positioned: capture the page.
    await expectThemedScreenshot(page, 'host-creation-panel');
  });

  test('drops the Notification section on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await openCreation(component);

    await expect(header(page)).toHaveText('Add a host');
    await expect(page.getByText(labelNotification)).toHaveCount(0);
    for (const section of [
      labelHostConfiguration,
      labelRelations,
      labelDataProcessing,
      labelHostExtendedInfos
    ]) {
      await expect(page.getByText(section).first()).toBeVisible();
    }
  });

  test('names a host that has no icon of its own', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openHost(component, 'host 1');

    await expect(header(page).locator('..').locator('img')).toHaveCount(0);
  });

  test('shows the host icon the listing carried into the panel', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(header(page).locator('..').locator('img')).toHaveAttribute(
      'alt',
      'server.png'
    );
  });
});

test.describe('existing host', () => {
  test('opens on the values the detail endpoint returns', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openHost(component, 'host 0');

    await expect(hostField(page, 'host-form-name')).toHaveValue(
      'host 0 as the detail endpoint spells it'
    );
    await expect(hostField(page, 'host-form-address')).toHaveValue(
      '10.10.10.10'
    );
    await expect(hostField(page, 'host-form-alias')).toHaveValue(
      'alias of host 0 as the detail endpoint spells it'
    );
    await expect(hostField(page, 'host-form-poller')).toHaveValue('Poller EU');
    // API Platform, not the legacy detail endpoint under `api/latest`.
    const { pathname } = api.details[0].urls[0];
    expect(pathname).toContain('/api/configuration/hosts/0');
    expect(pathname).not.toContain('/api/latest');
    // The header keeps naming the row, which is what the listing showed.
    await expect(header(page)).toHaveText('host 0');
    // The panel is fixed-positioned: capture the page.
    await expectThemedScreenshot(page, 'host-edition-panel');
  });

  test('saves an edited host back to the host it was opened on', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openHost(component, 'host 0');
    await expect(hostField(page, 'host-form-address')).toHaveValue(
      '10.10.10.10'
    );

    await hostField(page, 'host-form-address').fill('10.0.0.42');
    await save(page).click();

    // The same PATCH route enable and disable use, on host 0 and no other.
    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].urls[0].pathname).toContain(
      '/api/configuration/hosts/0'
    );
    expect(api.patch[0].bodies[0]).toEqual({
      address: '10.0.0.42',
      alias: 'alias of host 0 as the detail endpoint spells it',
      category_ids: [4],
      // The arguments go back as the list they came as.
      check_options: { args: ['3', '80%'], command_id: 9 },
      child_host_ids: [2],
      // Never read back, so off as legacy opens an existing host.
      create_services_linked_to_templates: false,
      data_processing: {
        acknowledgment_timeout: 15,
        check_freshness: 'true',
        event_handler_args: ['80', 'graceful'],
        event_handler_command_id: 7,
        event_handler_enabled: 'false',
        flap_detection_enabled: 'true',
        freshness_threshold: 120,
        high_flap_threshold: 50,
        low_flap_threshold: null
      },
      extended_informations: {
        action_url: 'https://example.com/actions/host-0',
        alt_icon: null,
        comment: 'Racked in room B',
        geo_coordinates: '48.8566,2.3522',
        icon_id: 12,
        note: 'Front web server',
        note_url: null
      },
      host_group_ids: [1],
      name: 'host 0 as the detail endpoint spells it',
      notifications: {
        contact_groups: [3],
        contacts: [1],
        enabled: 'false',
        first_delay: 1,
        interval: 3,
        options: ['down', 'recovery'],
        recovery_delay: null,
        timeperiod_id: 1
      },
      parent_host_ids: [1],
      poller_id: 2,
      scheduling_options: {
        active_check_enabled: 'false',
        check_timeperiod_id: 2,
        max_check_attempts: 3,
        normal_check_interval: 5,
        passive_check_enabled: 'true',
        retry_check_interval: null
      },
      severity_id: 2,
      snmp_version: '2c',
      template_ids: [6, 5],
      timezone_id: 7
    });
  });

  test('sends no alias for a host whose alias was cleared', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openHost(component, 'host 0');
    const alias = hostField(page, 'host-form-alias');
    await expect(alias).toHaveValue(
      'alias of host 0 as the detail endpoint spells it'
    );

    await alias.clear();
    await save(page).click();

    await expect.poll(() => api.patch[0].bodies.length).toBe(1);
    expect(api.patch[0].bodies[0]).toEqual(
      expect.objectContaining({ alias: null })
    );
  });
});

test.describe('read-only user', () => {
  test('sees the host in a read-only form', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { hasWriteAccess: false }
    );
    await openHost(component, 'host 0');

    // The host is still loaded and shown: read-only, not empty.
    const name = hostField(page, 'host-form-name');
    await expect(name).toHaveValue('host 0 as the detail endpoint spells it');
    await expect(name).toBeDisabled();
    for (const testId of [
      'host-form-address',
      'host-form-poller',
      'host-form-groups',
      'host-form-categories',
      'host-form-parent-hosts',
      'host-form-child-hosts',
      'host-form-notifications-contacts',
      'host-form-notifications-contact-groups',
      'host-form-notifications-timeperiod',
      'host-form-check-options-command',
      'host-form-scheduling-options-checkPeriod',
      // Host 0 has a command, so only read-only mode keeps its args disabled.
      'host-form-check-options-args',
      'host-form-notifications-interval'
    ]) {
      await expect(hostField(page, testId)).toBeDisabled();
    }
    await expect(
      page
        .getByTestId('host-form-notifications-enabled')
        .getByRole('button', { name: labelYes })
    ).toBeDisabled();
    await expect(
      page.getByTestId(`host-form-notifications-options-${labelDown}`)
    ).toHaveAttribute('aria-disabled', 'true');
    await expect(save(page)).toHaveCount(0);
  });

  test('cannot resolve an address', async ({ mockApi, mount, page }) => {
    const component = await setUp(
      { mockApi, mount },
      { hasWriteAccess: false }
    );
    await openHost(component, 'host 1');

    // A name, so only the missing write access disables it.
    await expect(hostField(page, 'host-form-address')).toHaveValue(
      'host-1.example.com'
    );
    await expect(resolve(page)).toBeDisabled();
  });

  test('does not look the default monitoring server up', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { hasWriteAccess: false }
    );
    await openHost(component, 'host 0');

    // The selector would answer 403, and the listing would say so.
    await expect(hostField(page, 'host-form-name')).toHaveValue(
      'host 0 as the detail endpoint spells it'
    );
    await expect(page.getByText(pollersForbiddenMessage)).toHaveCount(0);
  });
});

test.describe('validation', () => {
  test('refuses to save until the mandatory fields are filled', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openCreation(component);
    await expect(save(page)).toBeDisabled();

    await hostField(page, 'host-form-name').fill('srv-apache-02');
    await expect(save(page)).toBeDisabled();
  });

  for (const [testId, value, error] of [
    ['host-form-name', '_Module_BAM', labelNameMustNotStartWithModule],
    ['host-form-address', 'not a host!', labelInvalidAddress]
  ]) {
    test(`reports "${error}"`, async ({ mockApi, mount, page }) => {
      const component = await setUp({ mockApi, mount });
      await openCreation(component);
      await hostField(page, testId).fill(value);
      await hostField(page, testId).blur();

      await expect(page.getByText(error)).toBeVisible();
    });
  }
});

test.describe('address resolution', () => {
  const typeAddress = async (component: Locator, address: string) => {
    await openCreation(component);
    await hostField(component.page(), 'host-form-address').fill(address);
  };

  test('replaces the address with the IP its name resolves to', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await typeAddress(component, ` ${resolvedAddressResponse.hostname} `);
    await expect(resolve(page)).toHaveText(labelResolve);
    await resolve(page).click();

    await expect.poll(() => api.resolution.urls.length).toBe(1);
    const url = api.resolution.urls[0];
    expect(url.pathname).toContain('/api/configuration/hosts/_resolve');
    expect(url.pathname).not.toContain('/api/latest');
    expect(url.searchParams.get('hostname')).toBe(
      resolvedAddressResponse.hostname
    );
    await expect(hostField(page, 'host-form-address')).toHaveValue(
      resolvedAddressResponse.ip
    );
    await expect(
      page.getByText(
        `${resolvedAddressResponse.hostname} → ${resolvedAddressResponse.ip}`
      )
    ).toBeVisible();
  });

  test('keeps an address that does not resolve and says so', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { addressResolution: 'unresolved' }
    );
    await typeAddress(component, resolvedAddressResponse.hostname);
    await resolve(page).click();

    await expect(page.getByText(labelHostNotFound)).toBeVisible();
    await expect(hostField(page, 'host-form-address')).toHaveValue(
      resolvedAddressResponse.hostname
    );
  });

  test('shows why the API refuses to resolve an address', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { addressResolution: 'refused' }
    );
    await typeAddress(component, 'srv_01');
    await resolve(page).click();

    await expect(
      page.getByText(refusedAddressResponse.message.trim())
    ).toBeVisible();
    await expect(hostField(page, 'host-form-address')).toHaveValue('srv_01');
  });

  test('is offered only once there is an address', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openCreation(component);
    await expect(resolve(page)).toBeDisabled();

    // Blanks are trimmed before they are sent, so they count as nothing.
    const address = hostField(page, 'host-form-address');
    await address.fill('   ');
    await expect(resolve(page)).toBeDisabled();
    await address.fill('   srv-apache-02');
    await expect(resolve(page)).toBeEnabled();
  });

  test('is not offered for an address that already is an IP', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount });
    await openCreation(component);
    const address = hostField(page, 'host-form-address');

    for (const ip of ['10.0.0.42', 'fe80::1']) {
      await address.fill(ip);
      await expect(resolve(page)).toBeDisabled();
    }
    await address.fill('10.0.0.42.example.com');
    await expect(resolve(page)).toBeEnabled();
  });

  test('is not offered on a cloud platform', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { isCloudPlatform: true }
    );
    await typeAddress(component, 'srv-apache-02');

    await expect(resolve(page)).toHaveCount(0);
  });

  test('runs once while a lookup is running', async ({
    mockApi,
    mount,
    page
  }) => {
    // Unresolved, so the address stays a name: only the running lookup can
    // disable the button.
    const component = await setUp(
      { mockApi, mount },
      { addressResolution: 'unresolved', resolveDelay: 1000 }
    );
    await typeAddress(component, resolvedAddressResponse.hostname);
    await resolve(page).click();
    await expect(resolve(page)).toBeDisabled();
    await resolve(page).click({ force: true });

    await expect(page.getByText(labelHostNotFound)).toBeVisible();
    expect(api.resolution.urls).toHaveLength(1);
  });

  test('keeps an address edited while its previous value was resolving', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount }, { resolveDelay: 1000 });
    await typeAddress(component, resolvedAddressResponse.hostname);
    await resolve(page).click();
    await hostField(page, 'host-form-address').fill('srv-b');

    await expect.poll(() => api.resolution.urls.length).toBe(1);
    // Nothing visible marks the answer: give it time to land.
    await page.waitForTimeout(1500);
    await expect(hostField(page, 'host-form-address')).toHaveValue('srv-b');
    // Nor is a result it did not apply reported.
    await expect(page.getByText(resolvedAddressResponse.ip)).toHaveCount(0);
  });
});

test.describe('default monitoring server', () => {
  test('opens the creation form on it', async ({ mockApi, mount, page }) => {
    const component = await setUp({ mockApi, mount });
    await openCreation(component);

    await expect(hostField(page, 'host-form-poller')).toHaveValue('Poller US');
    await hostField(page, 'host-form-name').fill('srv-apache-02');
    await hostField(page, 'host-form-address').fill('10.0.0.42');
    await save(page).click();

    await expect.poll(() => api.creation.bodies.length).toBe(1);
    expect(api.creation.bodies[0]).toEqual(
      expect.objectContaining({ poller_id: 3 })
    );
  });

  test('keeps what was typed when it arrives late', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount }, { pollersDelay: 1500 });
    await openCreation(component);
    await hostField(page, 'host-form-name').fill('srv-apache-02');

    // Nothing visible changes when the response lands: give it time to.
    await page.waitForTimeout(2000);
    // A late default would reinitialise the form; this one goes without.
    await expect(hostField(page, 'host-form-name')).toHaveValue(
      'srv-apache-02'
    );
    await expect(hostField(page, 'host-form-poller')).toHaveValue('');
  });

  test('is left empty when no server is the default', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount },
      { hasDefaultPoller: false }
    );
    await openCreation(component);

    await expect(hostField(page, 'host-form-name')).toBeVisible();
    await expect(hostField(page, 'host-form-poller')).toHaveValue('');
  });
});

test('creates a host with its alias, trimmed', async ({
  mockApi,
  mount,
  page
}) => {
  const component = await setUp({ mockApi, mount });
  await openCreation(component);
  await expect(hostField(page, 'host-form-poller')).toHaveValue('Poller US');
  await hostField(page, 'host-form-name').fill('srv-apache-02');
  await hostField(page, 'host-form-alias').fill('  Apache front  ');
  await hostField(page, 'host-form-address').fill('10.0.0.42');
  await save(page).click();

  await expect.poll(() => api.creation.bodies.length).toBe(1);
  expect(api.creation.bodies[0]).toEqual(
    expect.objectContaining({ alias: 'Apache front' })
  );
});
