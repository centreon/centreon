import type { Page } from '@playwright/test';

import hostStatuses from '../../cypress/fixtures/Widgets/StatusChart/hostStatuses.json';
import hostStatusesNullValues from '../../cypress/fixtures/Widgets/StatusChart/hostStatusesNullValues.json';
import resourcesStatus from '../../cypress/fixtures/Widgets/StatusChart/resourcesStatus.json';
import serviceStatuses from '../../cypress/fixtures/Widgets/StatusChart/serviceStatuses.json';
import {
  options as baseOptions,
  hostStatus,
  resources,
  serviceStatus,
  totalHosts,
  totalServices
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-statuschart/src/spec/testUtils';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-statuschart/src/spec/StatusChart.
const storyId = 'centreon-widget-statuschart/src/StatusChart/Default';
const hostsEndpoint = './api/latest/monitoring/hosts/status';
const servicesEndpoint = './api/latest/monitoring/services/status';
const data = { resources };

const downHostsLink =
  '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';

const options = (extra: Record<string, unknown>) => ({
  ...baseOptions,
  ...extra
});

// Records window.open calls instead of the Cypress stub.
const recordWindowOpen = async (
  page: Page
): Promise<() => Promise<unknown>> => {
  await page.evaluate(() => {
    const opened: Array<string> = [];
    (window as unknown as { opened: Array<string> }).opened = opened;
    window.open = (url) => {
      opened.push(String(url));

      return null;
    };
  });

  return () =>
    page.evaluate(
      () => (window as unknown as { opened: Array<string> }).opened
    );
};

test.use({ viewport: { height: 768, width: 1366 } });

test('displays nothing when the API returns no data', async ({
  mockApi,
  mount
}) => {
  const hosts = await mockApi({ endpoint: hostsEndpoint, response: null });
  const component = await mount(storyId, {
    data,
    options: options({ displayType: 'donut', resourceTypes: ['host'] })
  });

  await expect.poll(() => hosts.urls.length).toBeGreaterThan(0);
  await expect(component.getByText('hosts')).toHaveCount(0);
});

test('says no host was found when the values are null', async ({
  mockApi,
  mount
}) => {
  await mockApi({ endpoint: hostsEndpoint, response: hostStatusesNullValues });
  const component = await mount(storyId, {
    data,
    options: options({ displayType: 'donut', resourceTypes: ['host'] })
  });

  await expect(component.getByText('No host found')).toBeVisible();
});

for (const displayType of ['donut', 'pie']) {
  test(`opens the resources status from a ${displayType} part`, async ({
    mockApi,
    mount,
    page
  }) => {
    await mockApi({ endpoint: hostsEndpoint, response: hostStatuses });
    const component = await mount(storyId, {
      data,
      options: options({ displayType, resourceTypes: ['host'] })
    });
    await expect(component.getByText('hosts').first()).toBeVisible();
    const opened = await recordWindowOpen(page);

    await component
      .locator('path[fill="#FF6E6E"]')
      .first()
      .click({
        position: { x: 10, y: 10 }
      });

    await expect.poll(opened).toEqual([downHostsLink]);
  });
}

test('reads a public widget from the public API', async ({
  mockApi,
  mount
}) => {
  const publicWidget = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1',
    response: hostStatuses
  });

  await mount(storyId, {
    data,
    isPublic: true,
    options: options({ displayType: 'donut', resourceTypes: ['host'] })
  });

  await expect.poll(() => publicWidget.urls.length).toBeGreaterThan(0);
});

const displayTypes = [
  { displayType: 'donut', label: 'Donut chart' },
  { displayType: 'pie', label: 'Pie chart' },
  { displayType: 'horizontal', label: 'Horizontal bar' },
  { displayType: 'vertical', label: 'Vertical bar' }
];

for (const { displayType, label } of displayTypes) {
  test.describe(label, () => {
    test.beforeEach(async ({ mockApi, page }) => {
      await page.clock.setFixedTime(new Date('2024-02-01T00:00:00Z'));
      await mockApi({
        endpoint: './api/latest/monitoring/resources',
        response: resourcesStatus
      });
      await mockApi({ endpoint: hostsEndpoint, response: hostStatuses });
      await mockApi({ endpoint: servicesEndpoint, response: serviceStatuses });
    });

    test('renders hosts and services by default', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(storyId, {
        data,
        options: options({ displayType })
      });

      await expect(
        component.locator(`[data-variant="${displayType}"]`).first()
      ).toBeAttached();
      await expect(component.getByText('212').first()).toBeVisible();
      await expect(component.getByText('hosts').first()).toBeVisible();
      await expect(component.getByText('678').first()).toBeVisible();
      await expect(component.getByText('services').first()).toBeVisible();
      await expect(component.getByTestId('Legend')).toHaveCount(2);
      await expect(component.getByTestId('value')).toHaveCount(9);
      await expect(
        component.getByTestId('value').first().locator('> *').first()
      ).toHaveText('19.8%');

      if (displayType === 'donut') {
        await expectThemedScreenshot(component, 'status-chart');
      }
    });

    const singleTypes = [
      { absent: '678 services', resourceType: 'host', shown: ['212', 'hosts'] },
      {
        absent: '212 hosts',
        resourceType: 'service',
        shown: ['678', 'services']
      }
    ];

    for (const { absent, resourceType, shown } of singleTypes) {
      test(`renders the ${resourceType}s only`, async ({ mount }) => {
        const component = await mount(storyId, {
          data,
          options: options({ displayType, resourceTypes: [resourceType] })
        });

        for (const text of shown) {
          await expect(component.getByText(text).first()).toBeVisible();
        }
        await expect(component.getByText(absent, { exact: true })).toHaveCount(
          0
        );
      });
    }

    for (const displayLegend of [false, true]) {
      test(`${displayLegend ? 'shows' : 'hides'} the legend`, async ({
        mount
      }) => {
        const component = await mount(storyId, {
          data,
          options: options({
            displayLegend,
            displayType,
            resourceTypes: ['service']
          })
        });

        await expect(component.getByTestId('Legend')).toHaveCount(
          displayLegend ? 1 : 0
        );
      });
    }

    const valueDisplays = [
      { expected: null, extra: { displayValues: false } },
      { expected: '5.8%', extra: { displayValues: true } },
      { expected: '39', extra: { unit: 'number' } }
    ];

    for (const { expected, extra } of valueDisplays) {
      test(`displays the values as ${expected ?? 'nothing'}`, async ({
        mount
      }) => {
        const component = await mount(storyId, {
          data,
          options: options({
            ...extra,
            displayType,
            resourceTypes: ['service']
          })
        });
        const values = component.getByTestId('value');

        if (expected === null) {
          await expect(component.getByText('services').first()).toBeVisible();
          await expect(values).toHaveCount(0);
        } else {
          await expect(values.first().locator('> *').first()).toHaveText(
            expected
          );
        }
      });
    }

    for (const resourceType of ['service', 'host']) {
      test(`details the ${resourceType} statuses in tooltips`, async ({
        mount,
        page
      }) => {
        const statuses = resourceType === 'host' ? hostStatus : serviceStatus;
        const total = resourceType === 'host' ? totalHosts : totalServices;
        const component = await mount(storyId, {
          data,
          options: options({ displayType, resourceTypes: [resourceType] })
        });

        for (const { count, status } of statuses.slice(0, -1)) {
          // Dispatched like cy.trigger(): small donut slices overlap under a real pointer.
          await component
            .getByTestId(status)
            .first()
            .dispatchEvent('mouseover');
          const tooltip = page.getByTestId(`tooltip-${status}`);
          for (const text of [
            `Status: ${status}`,
            `${count} ${resourceType}s`,
            'Disk-/',
            'Load',
            'Ping',
            'Centreon-Server',
            'Centreon-Server_17'
          ]) {
            await expect(tooltip).toContainText(text);
          }
        }

        const ok = statuses.at(-1);
        await component
          .getByTestId(ok?.status as string)
          .first()
          .dispatchEvent('mouseover');
        await expect(page.getByTestId(`tooltip-${ok?.status}`)).toContainText(
          `${ok?.count}/${total} ${resourceType}s are working fine.`
        );
        await expect(page.getByText('February 1, 2024').first()).toBeVisible();
      });
    }
  });
}
