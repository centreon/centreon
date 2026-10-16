import type { Locator, Page } from '@playwright/test';

import acknowledgement from '../../cypress/fixtures/Widgets/StatusGrid/acknowledgement.json';
import condensed from '../../cypress/fixtures/Widgets/StatusGrid/condensed.json';
import downtime from '../../cypress/fixtures/Widgets/StatusGrid/downtime.json';
import hostResources from '../../cypress/fixtures/Widgets/StatusGrid/hostResources.json';
import hostTooltipDetails from '../../cypress/fixtures/Widgets/StatusGrid/hostTooltipDetails.json';
import serviceResources from '../../cypress/fixtures/Widgets/StatusGrid/serviceResources.json';
import serviceTooltipDetails from '../../cypress/fixtures/Widgets/StatusGrid/serviceTooltipDetails.json';
import {
  labelAllMetricsAreWorkingFine,
  labelMetricName,
  labelSeeMore,
  labelValue
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-statusgrid/src/StatusGridStandard/translatedLabels';
import {
  condensedOptions,
  hostOptions,
  linkToAllRessource,
  linkToResourceCentreonPass,
  linkToResourceCentreonServer,
  linkToResourcePing,
  noResources,
  resources,
  resourcesRegex,
  seeMoreOptions,
  serviceOptions,
  services
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-statusgrid/src/spec/testUtils';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/translatedLabels';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-statusgrid/src/spec/StatusGrid.
const storyId = 'centreon-widget-statusgrid/src/StatusGrid/Default';
const resourcesEndpoint = 'monitoring/resources';
const publicWidgetEndpoint =
  'it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1';
const emptyData = { meta: { limit: 10, page: 1, total: 0 }, result: [] };
const downtimeText =
  'In downtime (from November 17, 2023 6:11 PM to November 17, 2023 7:11 PM)';

test.use({ viewport: { height: 600, width: 1100 } });

const mockAcknowledgements = (page: Page) =>
  page.route(/\/acknowledgements/, (route) =>
    route.fulfill({ json: acknowledgement })
  );

// The host listing and the host tooltip read the same endpoint, the tooltip
// with `types=["service"]`.
const mockHostRequests = async (
  mockApi: MockApi,
  page: Page,
  noValues = false
): Promise<Record<'listing' | 'public', MockedEndpoint>> => {
  await mockAcknowledgements(page);
  await mockApi({
    endpoint: 'monitoring/hosts/16/downtimes',
    response: downtime
  });

  return {
    listing: await mockApi({
      endpoint: resourcesEndpoint,
      response: (url: URL) =>
        url.searchParams.get('types')?.includes('service')
          ? hostTooltipDetails
          : noValues
            ? emptyData
            : hostResources
    }),
    public: await mockApi({
      endpoint: publicWidgetEndpoint,
      response: noValues ? emptyData : hostResources
    })
  };
};

const mockServiceRequests = async (
  mockApi: MockApi,
  page: Page,
  noValues = false
): Promise<Record<'listing' | 'metrics28', MockedEndpoint>> => {
  await mockAcknowledgements(page);
  await mockApi({
    endpoint: 'monitoring/hosts/14/services/19/downtimes',
    response: downtime
  });
  await mockApi({
    endpoint: 'monitoring/hosts/14/services/27/metrics',
    response: serviceTooltipDetails
  });

  return {
    listing: await mockApi({
      endpoint: resourcesEndpoint,
      response: noValues ? emptyData : serviceResources
    }),
    metrics28: await mockApi({
      endpoint: 'monitoring/hosts/14/services/28/metrics',
      response: serviceTooltipDetails
    })
  };
};

const mockStatusRequests = async (
  mockApi: MockApi
): Promise<Record<'public' | 'statuses' | 'tooltip', MockedEndpoint>> => ({
  public: await mockApi({
    endpoint: publicWidgetEndpoint,
    response: condensed
  }),
  // Slow, so the loading skeleton shows.
  statuses: await mockApi({
    delay: 2000,
    endpoint: 'monitoring/services/status',
    response: condensed
  }),
  tooltip: await mockApi({
    endpoint: resourcesEndpoint,
    response: hostTooltipDetails
  })
});

const fixClock = (page: Page) =>
  page.clock.setFixedTime(new Date('2021-02-01T00:00:00Z'));
// The tile surface is two levels above the status marker.
const tileOf = (marker: Locator) => marker.locator('xpath=../..');
const hover = (locator: Locator) => locator.dispatchEvent('mouseover');
const tooltip = (page: Page) => page.getByRole('tooltip');

test.describe('public widget', () => {
  test('reads the standard view from the public API', async ({
    mockApi,
    mount,
    page
  }) => {
    const mocks = await mockHostRequests(mockApi, page);
    await mount(storyId, {
      data: { resources },
      isPublic: true,
      options: hostOptions
    });

    await expect.poll(() => mocks.public.urls.length).toBeGreaterThan(0);
  });

  test('reads the condensed view from the public API', async ({
    mockApi,
    mount
  }) => {
    const mocks = await mockStatusRequests(mockApi);
    await mount(storyId, {
      data: { resources },
      isPublic: true,
      options: condensedOptions
    });

    await expect.poll(() => mocks.public.urls.length).toBeGreaterThan(0);
  });
});

test.describe('view by host', () => {
  test('displays a tile per status', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockHostRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources },
      options: hostOptions
    });

    for (const [name, status, color] of [
      ['Centreon-Server', 'up', 'rgb(159, 199, 78)'],
      ['Passive_server_1', 'down', 'rgb(255, 110, 110)'],
      ['Passive_server', 'unknown', 'rgb(227, 227, 227)']
    ]) {
      await expect(component.getByText(name, { exact: true })).toBeVisible();
      const marker = component.locator(`[data-status="${status}"]`).first();
      await expect(marker).toBeVisible();
      await expect(tileOf(marker)).toHaveCSS('background-color', color);
    }
    await expect(
      component.locator('[data-isindowntime="true"]').first()
    ).toHaveCSS('background-color', 'rgb(229, 216, 243)');
    await expect(component.getByTestId('HostIcon').first()).toBeVisible();
    await expectThemedScreenshot(component, 'status-grid-hosts');
  });

  test('describes a host in a tooltip', async ({ mockApi, mount, page }) => {
    await fixClock(page);
    await mockHostRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources },
      options: hostOptions
    });
    await hover(component.getByText('Passive_server_1', { exact: true }));

    await expect(
      page.locator('[data-resourceName="Passive_server_1"]')
    ).toHaveCSS('color', 'rgb(227, 227, 227)');
    for (const text of [
      'unknown (No output returned from host check)',
      downtimeText,
      'February 1, 2021'
    ]) {
      await expect(page.getByText(text).first()).toBeVisible();
    }
  });

  test('displays the tiles without selected resources', async ({
    mockApi,
    mount,
    page
  }) => {
    await mockHostRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources: noResources },
      options: hostOptions
    });

    const marker = component.locator('[data-status="up"]').first();
    await expect(
      component.getByText('Centreon-Server', { exact: true })
    ).toBeVisible();
    await expect(tileOf(marker)).toHaveCSS(
      'background-color',
      'rgb(159, 199, 78)'
    );
  });

  test('says when no host is found', async ({ mockApi, mount, page }) => {
    await fixClock(page);
    await mockHostRequests(mockApi, page, true);
    const component = await mount(storyId, {
      data: { resources },
      options: hostOptions
    });

    await expect(component.getByText(labelNoHostsFound)).toBeVisible();
  });

  test('searches the regex resources', async ({ mockApi, mount, page }) => {
    const mocks = await mockHostRequests(mockApi, page);
    await mount(storyId, {
      data: { resources: resourcesRegex },
      options: hostOptions
    });

    await expect
      .poll(() => mocks.listing.urls[0]?.searchParams.get('search'))
      .toBe(
        '{"$and":[{"$or":[{"parent_name":{"$rg":"^H1$"}}]},{"$or":[{"name":{"$rg":"^Loa"}}]}]}'
      );
  });

  test('links a host tile to the resource status page', async ({
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockHostRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources },
      options: hostOptions
    });

    await expect(
      component.getByTestId('link to Centreon-Server')
    ).toHaveAttribute('href', linkToResourceCentreonServer);
  });
});

test.describe('view by service', () => {
  test('displays a tile per status, with its state', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockServiceRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources },
      options: serviceOptions
    });

    for (const { color, eq, name, status } of services) {
      await expect(component.getByText(name, { exact: true })).toBeVisible();
      const marker = component.locator(`[data-status="${status}"]`).nth(eq);
      await expect(marker).toBeVisible();
      await expect(tileOf(marker)).toHaveCSS('background-color', color);
    }
    await expect(
      component.locator('[data-isindowntime="true"]').first()
    ).toHaveCSS('background-color', 'rgb(229, 216, 243)');
    await expect(
      component.locator('[data-isacknowledged="true"]').first()
    ).toHaveCSS('background-color', 'rgb(223, 210, 185)');
    await expect(component.getByTestId('ServiceIcon').first()).toBeVisible();
    await expectThemedScreenshot(component, 'status-grid-services');
  });

  test('displays the tiles without selected resources', async ({
    mockApi,
    mount,
    page
  }) => {
    await mockServiceRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources: noResources },
      options: serviceOptions
    });

    for (const { color, eq, name, status } of services) {
      await expect(component.getByText(name, { exact: true })).toBeVisible();
      await expect(
        tileOf(component.locator(`[data-status="${status}"]`).nth(eq))
      ).toHaveCSS('background-color', color);
    }
  });

  const hoverService = async (
    mount: (id: string, props?: object) => Promise<Locator>,
    page: Page,
    name: string,
    color: string
  ) => {
    const component = await mount(storyId, {
      data: { resources },
      options: serviceOptions
    });
    await hover(component.getByText(name, { exact: true }));
    await expect(page.locator(`[data-resourcename="${name}"]`)).toHaveCSS(
      'color',
      color
    );
    await expect(page.locator('[data-parentstatus="5"]').first()).toBeVisible();
    await expect(
      page.getByText('Centreon-Server', { exact: true })
    ).toHaveCount(7);
    await expect(page.getByText('February 1, 2021').first()).toBeVisible();
  };

  test('describes an ok service', async ({ mockApi, mount, page }) => {
    await fixClock(page);
    await mockServiceRequests(mockApi, page);
    await hoverService(mount, page, 'Ping', 'rgb(159, 199, 78)');

    await expect(page.getByText(labelAllMetricsAreWorkingFine)).toBeVisible();
  });

  for (const [name, color] of [
    ['Centreon_Pass', 'rgb(255, 110, 110)'],
    ['Passive', 'rgb(252, 196, 129)']
  ]) {
    test(`describes the metrics of the ${name} service`, async ({
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      await mockServiceRequests(mockApi, page);
      await hoverService(mount, page, name, color);

      await expect(page.getByText(labelMetricName).first()).toBeAttached();
      await expect(page.getByText(labelValue).first()).toBeAttached();
      await expect(
        page.getByText('rta', { exact: true }).first()
      ).toBeVisible();
      await expect(
        // The warning-coloured value of the rta metric.
        tooltip(page).getByText('1 (ms)', { exact: true })
      ).toHaveCSS('color', 'rgb(252, 196, 129)');
    });
  }

  for (const [name, texts] of [
    [
      'Memory',
      [
        'unknown (Execute command failed)',
        'Acknowledged (Acknowledged by admin)'
      ]
    ],
    ['Disk-/', ['unknown (Execute command failed)', downtimeText]],
    ['Load', ['unknown (Execute command failed)']]
  ] as const) {
    test(`describes the ${name} service`, async ({ mockApi, mount, page }) => {
      await fixClock(page);
      await mockServiceRequests(mockApi, page);
      await hoverService(mount, page, name, 'rgb(227, 227, 227)');

      for (const text of texts) {
        await expect(page.getByText(text).first()).toBeVisible();
      }
    });
  }

  test('says when no service is found', async ({ mockApi, mount, page }) => {
    await fixClock(page);
    await mockServiceRequests(mockApi, page, true);
    const component = await mount(storyId, {
      data: { resources },
      options: serviceOptions
    });

    await expect(component.getByText(labelNoServicesFound)).toBeVisible();
  });

  test('searches the regex resources', async ({ mockApi, mount, page }) => {
    const mocks = await mockServiceRequests(mockApi, page);
    await mount(storyId, {
      data: { resources: resourcesRegex },
      options: serviceOptions
    });

    await expect
      .poll(() => mocks.listing.urls[0]?.searchParams.get('search'))
      .toBe(
        '{"$and":[{"$or":[{"parent_name":{"$rg":"^H1$"}}]},{"$or":[{"name":{"$rg":"^Loa"}}]}]}'
      );
  });
});

test.describe('links to the resource status page', () => {
  test('links the tiles and the see more tile', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockServiceRequests(mockApi, page);
    const component = await mount(storyId, {
      data: { resources },
      options: seeMoreOptions
    });

    await expect(component.getByText('Ping', { exact: true })).toBeVisible();
    await expect(component.getByText(labelSeeMore)).toBeVisible();
    await expect(component.getByTestId('link to Ping')).toHaveAttribute(
      'href',
      linkToResourcePing
    );
    await expect(
      component.getByTestId('link to Centreon_Pass')
    ).toHaveAttribute('href', linkToResourceCentreonPass);
    await expect(component.getByLabel(labelSeeMore)).toHaveAttribute(
      'href',
      linkToAllRessource
    );
    await expectThemedScreenshot(component, 'status-grid-see-more');
  });
});

test.describe('condensed view', () => {
  const okLink =
    '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';

  const expectStatusTiles = async (component: Locator) => {
    for (const text of [
      '65.86m services',
      'ok',
      '52.9m',
      'critical',
      '98.9m',
      'pending',
      '0'
    ]) {
      await expect(
        component.getByText(text, { exact: true }).first()
      ).toBeVisible();
    }
  };

  test('shows a skeleton, then the status tiles', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockStatusRequests(mockApi);
    const component = await mount(storyId, {
      data: { resources },
      options: condensedOptions
    });

    await expect(
      component.locator('[data-skeleton="true"]').first()
    ).toBeVisible();
    await expectStatusTiles(component);
    await expect(component.locator('[data-label="ok"]')).toHaveAttribute(
      'href',
      okLink
    );
    await expectThemedScreenshot(component, 'status-grid-condensed');
  });

  test('displays the status tiles without selected resources', async ({
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockStatusRequests(mockApi);
    const component = await mount(storyId, {
      data: { resources: noResources },
      options: condensedOptions
    });

    await expectStatusTiles(component);
  });

  test('searches the regex resources', async ({ mockApi, mount }) => {
    const mocks = await mockStatusRequests(mockApi);
    await mount(storyId, {
      data: { resources: resourcesRegex },
      options: condensedOptions
    });

    await expect
      .poll(() => mocks.statuses.urls[0]?.searchParams.get('search'))
      .toBe(
        '{"$and":[{"$or":[{"name":{"$rg":"^Loa"}}]},{"$or":[{"host.name":{"$rg":"^H1$"}}]}]}'
      );
  });

  test('describes each status in a tooltip', async ({
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockStatusRequests(mockApi);
    const component = await mount(storyId, {
      data: { resources },
      options: condensedOptions
    });
    await expectStatusTiles(component);

    await hover(component.locator('[data-label="ok"]'));
    await expect(page.getByText('February 1, 2021').first()).toBeVisible();
    await expect(
      page.getByText('52.9m/65.86m services are working fine.')
    ).toBeVisible();

    await hover(component.locator('[data-label="critical"]'));
    for (const text of ['Status: Critical', '98.9m services', 'Passive_1']) {
      await expect(tooltip(page).getByText(text).first()).toBeVisible();
    }
    await expect(
      tooltip(page).getByText('Passive', { exact: true })
    ).toBeVisible();

    await hover(component.locator('[data-label="pending"]'));
    await expect(tooltip(page).getByText('Status: Pending')).toBeVisible();
    await expect(
      tooltip(page).getByText('No service found with this status')
    ).toBeVisible();
  });
});
