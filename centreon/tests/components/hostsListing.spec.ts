import type { Locator } from '@playwright/test';

import {
  labelClear,
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable,
  labelEnableDisable,
  labelFilters,
  labelMoreActions,
  labelSearch
} from '../../www/front_src/src/Configuration/ConfigurationBase/translatedLabels';
import { hostIcon } from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import {
  labelAlias,
  labelDeployServices,
  labelHostGroup,
  labelHosts,
  labelHostTemplate,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelServiceDeploymentFailed,
  labelServicesDeployed,
  labelStatus,
  labelTemplates,
  labelWelcomeToHosts
} from '../../www/front_src/src/Configuration/Hosts/translatedLabels';
import { expect, test } from './fixtures';
import { type HostsApi, hostsStoryId, mockHostsApi } from './hostsApi';

// Migrated from Configuration/Hosts/Specs (Listing, Columns, Filters and
// Actions suites).
let api: HostsApi;

test.use({ viewport: { height: 900, width: 1440 } });

const mountHosts = async (
  mount: (id: string, props?: object) => Promise<Locator>,
  props = {}
) => {
  const component = await mount(hostsStoryId, props);
  await expect(component.getByText('host 0', { exact: true })).toBeVisible();

  return component;
};

const row = (component: Locator, name: string) =>
  component
    .getByRole('row')
    .filter({ has: component.page().getByText(name, { exact: true }) });

const lastListingParam = (key: string) => () =>
  api.listing.urls.at(-1)?.searchParams.get(key) ?? null;

test.describe('listing', () => {
  test.beforeEach(async ({ mockApi }) => {
    api = await mockHostsApi(mockApi);
  });

  test('reads the listing from API Platform, not the legacy route', async ({
    mount
  }) => {
    const component = await mountHosts(mount);

    const url = api.listing.urls[0];
    expect(url.pathname).toContain('/api/configuration/hosts');
    expect(url.pathname).not.toContain('/api/latest');
    expect(url.searchParams.get('page')).toBe('1');
    expect(url.searchParams.get('itemsPerPage')).toBe('10');
    await expect(component.getByText(labelHosts).first()).toBeVisible();
  });

  test('displays every column and the hosts returned by the API', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mountHosts(mount);

    for (const label of [
      labelName,
      labelAlias,
      labelIpAddress,
      labelMonitoringServer,
      labelTemplates
    ]) {
      // Unsortable columns have no label: match the header text.
      await expect(
        component
          .locator('[role="row"]')
          .first()
          .getByText(label, { exact: true })
      ).toBeVisible();
    }
    const host0 = row(component, 'host 0');
    for (const text of ['alias for host 0', '10.0.0.0', 'Central']) {
      await expect(host0.getByText(text, { exact: true })).toBeVisible();
    }
    await expectThemedScreenshot(component, 'hosts-listing');
  });

  test('decodes a host whose optional fields are absent', async ({ mount }) => {
    const component = await mountHosts(mount);

    await expect(
      row(component, 'host 1').getByText('10.0.0.1', { exact: true })
    ).toBeVisible();
    await expect(component.getByText('alias for host 1')).toHaveCount(0);
  });

  test('renders the icon at the URL the API returned', async ({ mount }) => {
    const component = await mountHosts(mount);

    // The exact src proves no prefix is prepended to the icon URL.
    await expect(component.getByTestId('server.png')).toHaveAttribute(
      'src',
      hostIcon
    );
    await expect(
      row(component, 'host 1').getByTestId('HostIcon')
    ).toBeAttached();
  });

  test('links each template to its configuration page', async ({ mount }) => {
    const component = await mountHosts(mount);

    for (const [id, name] of [
      [5, 'generic-active-host'],
      [6, 'generic-passive-host']
    ]) {
      const link = component.getByTestId(`host-template-link_${id}`);
      await expect(link).toHaveText(String(name));
      await expect(link).toHaveAttribute(
        'href',
        `/main.php?p=60103&o=c&host_id=${id}`
      );
    }
    await expect(row(component, 'host 0')).toContainText(
      'generic-active-host | generic-passive-host'
    );
    await expect(
      row(component, 'host 1').locator('[data-testid^="host-template-link_"]')
    ).toHaveCount(0);
  });
});

test('displays the welcome page when no host exists', async ({
  expectThemedScreenshot,
  mockApi,
  mount
}) => {
  api = await mockHostsApi(mockApi, { isEmpty: true });
  const component = await mount(hostsStoryId);

  await expect(component.getByText(labelWelcomeToHosts)).toBeVisible();
  await expectThemedScreenshot(component, 'hosts-welcome');
});

test.describe('filters', () => {
  test.beforeEach(async ({ mockApi }) => {
    api = await mockHostsApi(mockApi);
  });

  // The advanced filters open in a portalled popover.
  const openAdvancedFilters = async (component: Locator) => {
    await component.getByTestId(labelFilters).click();
    const filters = component.page().getByTestId('advanced-filters');
    await expect(filters).toBeVisible();

    return filters;
  };
  // Options are queried from the popper: template names also appear in the
  // listing behind it.
  const select = async (filters: Locator, label: string, option: string) => {
    await filters.getByTestId(label).click();
    await filters
      .page()
      .locator('.MuiAutocomplete-popper')
      .getByText(option, { exact: true })
      .click();
  };

  test('offers the five filters of the listing', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mountHosts(mount);
    const filters = await openAdvancedFilters(component);

    for (const label of [
      labelName,
      labelHostGroup,
      labelHostTemplate,
      labelMonitoringServer,
      labelStatus
    ]) {
      await expect(filters.getByText(label).first()).toBeVisible();
    }
    await expectThemedScreenshot(filters, 'hosts-advanced-filters');
  });

  for (const [label, option, param, value] of [
    [labelHostGroup, 'Linux servers', 'group_id', '1'],
    [labelHostTemplate, 'generic-active-host', 'template_id', '5'],
    [labelMonitoringServer, 'Poller EU', 'poller_id', '2']
  ]) {
    test(`sends the ${label.toLowerCase()} as \`${param}\``, async ({
      mount
    }) => {
      const component = await mountHosts(mount);
      const filters = await openAdvancedFilters(component);
      await select(filters, label, option);
      await filters.getByTestId(labelSearch).click();

      await expect.poll(lastListingParam(param)).toBe(value);
    });
  }

  test('sends the status as `activated`, the parameter this endpoint takes', async ({
    mount
  }) => {
    const component = await mountHosts(mount);
    const filters = await openAdvancedFilters(component);
    await filters
      .getByText(labelStatus, { exact: true })
      .locator('..')
      .locator('input')
      .first()
      .click();
    await filters.getByTestId(labelSearch).click();

    await expect.poll(lastListingParam('activated')).toBe('true');
    expect(lastListingParam('is_activated')()).toBeNull();
  });

  test('combines the filters, then clears them all', async ({ mount }) => {
    const component = await mountHosts(mount);
    const filters = await openAdvancedFilters(component);
    await select(filters, labelHostGroup, 'Linux servers');
    await select(filters, labelHostTemplate, 'generic-active-host');
    await filters.getByTestId(labelSearch).click();
    await expect.poll(lastListingParam('group_id')).toBe('1');
    expect(lastListingParam('template_id')()).toBe('5');

    await filters.getByTestId(labelClear).click();
    await expect.poll(lastListingParam('group_id')).toBeNull();
    expect(lastListingParam('template_id')()).toBeNull();
  });

  test('keeps the filters in local storage', async ({ mount, page }) => {
    const component = await mountHosts(mount);
    const filters = await openAdvancedFilters(component);
    await select(filters, labelHostGroup, 'Linux servers');
    await filters.getByTestId(labelSearch).click();
    await expect.poll(lastListingParam('group_id')).toBe('1');

    const stored = await page.evaluate(() =>
      JSON.parse(window.localStorage.getItem('filters_hosts') ?? '{}')
    );
    expect(stored.group_id).toEqual({ id: 1, name: 'Linux servers' });
  });
});

test.describe('actions', () => {
  // A deactivated row is not selectable: index the enabled checkboxes, after
  // the select-all one.
  const selectFirstSelectableRow = (component: Locator) =>
    component.locator('input[type="checkbox"]:not([disabled])').nth(1).click();
  const selectAll = (component: Locator) =>
    component.locator('input[type="checkbox"]').first().click();
  const openMoreActions = async (component: Locator) => {
    await component.getByTestId(labelMoreActions).click();

    return component.page().getByRole('menu');
  };

  test.describe('with write access', () => {
    test.beforeEach(async ({ mockApi }) => {
      api = await mockHostsApi(mockApi);
    });

    test('links the services icon to the legacy services listing', async ({
      mount,
      page
    }) => {
      const component = await mountHosts(mount);
      await page.evaluate(() => {
        window.open = (...args) => {
          (window as unknown as { opened: unknown }).opened = args;

          return null;
        };
      });
      await component.getByTestId('go-to-services_0').click();

      await expect
        .poll(() =>
          page.evaluate(() => (window as unknown as { opened: unknown }).opened)
        )
        .toEqual(['/main.php?p=60201&search=host%200', '_self']);
    });

    test('deletes a host through the confirmation modal', async ({
      expectThemedScreenshot,
      mount,
      page
    }) => {
      const component = await mountHosts(mount);
      await component.getByTestId(`${labelDelete}_0`).click();
      await expectThemedScreenshot(
        page.getByRole('dialog'),
        'hosts-delete-confirmation'
      );
      await page.getByTestId('confirm').click();

      await expect.poll(() => api.deletion[0].urls.length).toBe(1);
      const { pathname } = api.deletion[0].urls[0];
      expect(pathname).toContain('/api/configuration/hosts/0');
      expect(pathname).not.toContain('/api/latest');
    });

    test('duplicates a host through the confirmation modal', async ({
      mount,
      page
    }) => {
      const component = await mountHosts(mount);
      await component.getByTestId(`${labelDuplicate}_0`).click();
      await page.getByTestId('confirm').click();

      await expect.poll(() => api.duplication.urls.length).toBe(1);
      expect(api.duplication.urls[0].pathname).not.toContain('/api/latest');
    });

    test('deletes every selected host, one request each', async ({
      mount,
      page
    }) => {
      const component = await mountHosts(mount);
      await selectAll(component);
      const menu = await openMoreActions(component);
      await menu.getByText(labelDelete, { exact: true }).click();
      await page.getByTestId('confirm').click();

      await expect.poll(() => api.deletion[2].urls.length).toBe(1);
      expect(api.deletion[0].urls).toHaveLength(1);
    });

    test('disables an activated host from the row toggle', async ({
      mount
    }) => {
      const component = await mountHosts(mount);
      await component.getByTestId(`${labelEnableDisable}_0`).click();

      await expect
        .poll(() => api.patch[0].bodies)
        // `activated`, the key the endpoint reads: any other is ignored.
        .toEqual([{ activated: false }]);
      expect(api.patch[0].urls[0].pathname).not.toContain('/api/latest');
    });

    test('enables a deactivated host from the row toggle', async ({
      mount
    }) => {
      const component = await mountHosts(mount);
      await component.getByTestId(`${labelEnableDisable}_1`).click();

      await expect
        .poll(() => api.patch[1].bodies)
        .toEqual([{ activated: true }]);
    });

    test('offers the massive actions, and no massive change', async ({
      mount
    }) => {
      const component = await mountHosts(mount);
      await selectFirstSelectableRow(component);
      const menu = await openMoreActions(component);

      await expect(menu.getByRole('menuitem')).toHaveText([
        labelDuplicate,
        labelEnable,
        labelDisable,
        labelDelete,
        labelDeployServices
      ]);
    });

    test('disables every selected host, not just the first', async ({
      mount
    }) => {
      const component = await mountHosts(mount);
      // Hosts 0 and 2: host 1 is deactivated and cannot be selected.
      await selectAll(component);
      const menu = await openMoreActions(component);
      await menu.getByText(labelDisable, { exact: true }).click();

      await expect
        .poll(() => api.patch[2].bodies)
        .toEqual([{ activated: false }]);
    });

    test('deploys the services of the selected host', async ({
      mount,
      page
    }) => {
      const component = await mountHosts(mount);
      await selectFirstSelectableRow(component);
      const menu = await openMoreActions(component);
      await menu.getByText(labelDeployServices).click();

      await expect.poll(() => api.deployment.urls.length).toBe(1);
      await expect(page.getByText(labelServicesDeployed)).toBeVisible();
    });
  });

  test('reports a failed deployment with the agreed message, once', async ({
    mockApi,
    mount,
    page
  }) => {
    api = await mockHostsApi(mockApi, { deployFails: true });
    const component = await mountHosts(mount);
    await selectFirstSelectableRow(component);
    const menu = await openMoreActions(component);
    await menu.getByText(labelDeployServices).click();

    await expect(page.getByText(labelServiceDeploymentFailed)).toBeVisible();
    // The API's own message is suppressed so the two do not stack.
    await expect(page.getByText('Host not found')).toHaveCount(0);
    await expect(page.getByText(labelServicesDeployed)).toHaveCount(0);
  });

  test.describe('read-only user', () => {
    test.beforeEach(async ({ mockApi }) => {
      api = await mockHostsApi(mockApi, { hasWriteAccess: false });
    });

    test('keeps the services icon and a disabled toggle', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mountHosts(mount, { hasWriteAccess: false });

      await expect(component.getByTestId('go-to-services_0')).toBeVisible();
      await expect(
        component.getByTestId(`${labelEnableDisable}_0`).locator('input')
      ).toBeDisabled();
      await expectThemedScreenshot(component, 'hosts-listing-read-only');
    });

    test('hides the add button, more actions and row write actions', async ({
      mount
    }) => {
      const component = await mountHosts(mount, { hasWriteAccess: false });

      for (const testId of [
        labelMoreActions,
        'add-resource',
        `${labelDelete}_0`,
        `${labelDuplicate}_0`
      ]) {
        await expect(component.getByTestId(testId)).toHaveCount(0);
      }
    });
  });
});
