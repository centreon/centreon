import topBottom from '../../cypress/fixtures/Widgets/Graph/topBottom.json';
import topMetricsWithLongRSname from '../../cypress/fixtures/Widgets/Graph/topMetricsWithLongRSname.json';
import topMetricsWithUniqueRS from '../../cypress/fixtures/Widgets/Graph/topMetricsWithUniqueRS.json';
import { labelPreviewRemainsEmpty } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-topbottom/src/TopBottom.
const storyId = 'centreon-widget-topbottom/src/Widget/Default';
const topEndpoint = './api/latest/monitoring/dashboard/metrics/top';

const storageMetric = [{ id: 2, name: 'C:#storage', unit: 'B' }];
const host = { resources: [{ id: 1, name: 'H1' }], resourceType: 'host' };
const widgetData = {
  metrics: storageMetric,
  resources: [
    { resources: [{ id: 1, name: 'HG1' }], resourceType: 'host-group' },
    host
  ]
};
const bottomFive = {
  numberOfValues: 5,
  order: 'bottom',
  showLabels: true
} as const;

const linkToPing1 =
  '/monitoring/resources?details=%7B%22id%22%3A%221%22%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2F1%2Fservices%2F1%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22h1-s1%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbPing_1%5C%5Cb%22%2C%22name%22%3A%22Ping_1%22%7D%5D%7D%2C%7B%22name%22%3A%22h.name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbCentreon_server%5C%5Cb%22%2C%22name%22%3A%22Centreon_server%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';

const pings = [1, 2, 3, 4].map(
  (index) => `#${index} Centreon_server_Ping_${index}`
);
const values = ['10 B', '20 B', '30 B', '40 B'];

test.use({ viewport: { height: 800, width: 1280 } });

test('sends a public widget to the public API', async ({ mockApi, mount }) => {
  const publicWidget = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1',
    response: widgetData
  });
  await mockApi({ endpoint: topEndpoint, response: topBottom });

  await mount(storyId, { data: widgetData, isPublic: true });

  await expect.poll(() => publicWidget.urls.length).toBeGreaterThan(0);
});

test('asks for data when the dataset is empty', async ({ mount }) => {
  const component = await mount(storyId);

  await expect(component.getByText(labelPreviewRemainsEmpty)).toBeVisible();
});

test.describe('with the top metrics', () => {
  let top: { urls: Array<URL> };

  test.beforeEach(async ({ mockApi }) => {
    top = await mockApi({ endpoint: topEndpoint, response: topBottom });
  });

  test('displays the top values', async ({ expectThemedScreenshot, mount }) => {
    const component = await mount(storyId, { data: widgetData });

    for (const text of [...pings, ...values]) {
      await expect(component.getByText(text)).toBeVisible();
    }
    expect(top.urls[0]?.search).toBe(
      '?limit=10&sort_by=%7B%22current_value%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=C%3A%23storage'
    );

    await expectThemedScreenshot(component, 'top-bottom');
  });

  test('requests the bottom values of the configured size', async ({
    mount
  }) => {
    await mount(storyId, { data: widgetData, topBottomSettings: bottomFive });

    await expect
      .poll(() => top.urls[0]?.search)
      .toBe(
        '?limit=5&sort_by=%7B%22current_value%22%3A%22DESC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%2C%7B%22host.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D&metric_name=C%3A%23storage'
      );
  });

  test('hides the value labels', async ({ mount }) => {
    const component = await mount(storyId, {
      data: widgetData,
      topBottomSettings: { ...bottomFive, showLabels: false }
    });

    for (const ping of pings) {
      await expect(component.getByText(ping)).toBeVisible();
    }
    for (const value of values) {
      await expect(component.getByText(value)).toHaveCount(0);
    }
  });

  test('links the resource names and bars to the resources page', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      data: widgetData,
      topBottomSettings: { ...bottomFive, showLabels: false }
    });
    const links = component.getByTestId('link to Ping_1');

    await expect(links).toHaveCount(2);
    await expect(links.nth(0)).toHaveAttribute('href', linkToPing1);
    await expect(links.nth(1)).toHaveAttribute('href', linkToPing1);
  });

  test('searches the regex resources', async ({ mount }) => {
    await mount(storyId, {
      data: {
        metrics: storageMetric,
        resources: [{ resources: '^Loa', resourceType: 'service' }, host]
      }
    });

    await expect
      .poll(() => top.urls[0]?.searchParams.get('search'))
      .toBe(
        '{"$and":[{"$and":[{"host.id":{"$in":[1]}}]},{"$and":[{"$or":[{"name":{"$rg":"^Loa"}}]}]}]}'
      );
  });

  test('searches a meta service', async ({ mount }) => {
    await mount(storyId, {
      data: {
        metrics: [{ id: 1, name: 'free', unit: '' }],
        resources: [
          { resources: [{ id: 1, name: 'M1' }], resourceType: 'meta-service' }
        ]
      }
    });

    await expect.poll(() => top.urls.length).toBeGreaterThan(0);
    expect(top.urls[0]?.searchParams.get('search')).toBe(
      '{"$and":[{"metaservice.id":{"$in":[1]}}]}'
    );
    expect(top.urls[0]?.searchParams.get('metrics_names')).toBeNull();
  });
});

const widths = [1024, 600, 350];

for (const width of widths) {
  test.describe(`at ${width}px`, () => {
    test.use({ viewport: { height: 590, width } });

    test('fits long resource names', async ({ mockApi, mount }) => {
      await mockApi({
        endpoint: topEndpoint,
        response: topMetricsWithLongRSname
      });
      const component = await mount(storyId, { data: widgetData });

      for (const text of [
        '#1 Centreon_server_Ping_1',
        '#2 Centreon_server_Ping_2',
        '#3 Centreon_server_exmaple_200_chars_',
        '10 B',
        '20 B',
        '40 B'
      ]) {
        await expect(component.getByText(text).first()).toBeVisible();
      }
    });

    test('renders a single resource', async ({ mockApi, mount }) => {
      await mockApi({
        endpoint: topEndpoint,
        response: topMetricsWithUniqueRS
      });
      const component = await mount(storyId, { data: widgetData });

      await expect(
        component.getByText('#1 Centreon_server_Ping_1')
      ).toBeVisible();
      await expect(component.getByText('10 B')).toBeVisible();
    });
  });
}
