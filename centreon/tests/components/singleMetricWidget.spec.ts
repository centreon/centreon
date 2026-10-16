import type { Locator, Page } from '@playwright/test';

import metrics from '../../cypress/fixtures/Widgets/Graph/metrics.json';
import metricWithBytUnit from '../../cypress/fixtures/Widgets/Graph/metricWithBytUnit.json';
import { labelPreviewRemainsEmpty } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-singlemetric/src/SingleMetric.
// The gallery interpolates the threshold tooltips; the Cypress spec asserted the
// raw {{metric}} template. Exact bar widths in pixels are not asserted.
const storyId = 'centreon-widget-singlemetric/src/SingleMetric/Default';

const serviceResources = [
  { resources: [{ id: 1, name: 'HG1' }], resourceType: 'host-group' },
  { resources: [{ id: 1, name: 'Service_1' }], resourceType: 'service' }
];
const pingData = {
  metrics: [
    {
      id: 1,
      name: 'Ping_1',
      serviceId: 1,
      serviceName: 'Service_1',
      unit: 'ms'
    },
    { id: 2, name: 'Cpu 1', unit: '%' },
    { id: 3, name: 'Cpu 2', unit: '%' }
  ],
  resources: serviceResources
};
const diskData = {
  metrics: [
    { id: 1, name: 'Disk', serviceId: 1, serviceName: 'Service_1', unit: 'B' }
  ],
  resources: serviceResources
};

const threshold = (extra: Record<string, unknown> = {}) => ({
  criticalType: 'default',
  customCritical: 0,
  customWarning: 0,
  enabled: true,
  warningType: 'default',
  ...extra
});
const thresholds = {
  critical: threshold({
    criticalType: 'custom',
    customCritical: 20,
    customWarning: 10,
    warningType: 'custom'
  }),
  default: threshold(),
  disabled: threshold({ enabled: false }),
  warning: threshold({ customWarning: 20, warningType: 'custom' })
};

const colors = {
  critical: 'rgb(255, 74, 74)',
  default: 'rgb(19, 29, 90)',
  success: 'rgb(136, 185, 34)',
  warning: 'rgb(253, 155, 39)'
};

test.use({ viewport: { height: 768, width: 1366 } });

const mockMetrics = async (
  mockApi: (options: {
    endpoint: string;
    response: unknown;
  }) => Promise<unknown>,
  metricName: string,
  response: unknown
): Promise<void> => {
  await mockApi({
    endpoint: `./api/latest/monitoring/hosts/1/services/1/metrics/${encodeURIComponent(metricName)}`,
    response
  });
};

const mountWidget = (
  mount: (id: string, props: Record<string, unknown>) => Promise<Locator>,
  displayType: string,
  thresholdOptions: Record<string, unknown>,
  { data = pingData, valueFormat = 'human' } = {}
): Promise<Locator> =>
  mount(storyId, {
    data,
    options: { displayType, threshold: thresholdOptions, valueFormat }
  });

const tooltipOf = async (page: Page, target: Locator, text: string) => {
  await target.dispatchEvent('mouseover');
  await expect(page.getByText(text).first()).toBeVisible();
};

test('reads a public widget from the public API', async ({
  mockApi,
  mount
}) => {
  const publicWidget = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1',
    response: metrics
  });

  await mount(storyId, {
    data: pingData,
    isPublic: true,
    options: {
      displayType: 'text',
      threshold: thresholds.default,
      valueFormat: 'human'
    }
  });

  await expect.poll(() => publicWidget.urls.length).toBeGreaterThan(0);
});

test('asks for a metric when none is selected', async ({ mount }) => {
  const component = await mount(storyId, {
    data: {
      metrics: [],
      resources: [
        { resources: [], resourceType: 'host' },
        { resources: [], resourceType: 'service' }
      ]
    },
    options: { displayType: 'text', threshold: thresholds.disabled }
  });

  await expect(component.getByText(labelPreviewRemainsEmpty)).toBeVisible();
});

test('reads the metric of a meta service', async ({ mockApi, mount }) => {
  const metaService = await mockApi({
    endpoint: './api/latest/monitoring/metaservice/1/metrics/free',
    response: metrics
  });

  await mount(storyId, {
    data: {
      metrics: [{ id: 1, name: 'free', unit: '' }],
      resources: [
        { resources: [{ id: 1, name: 'M1' }], resourceType: 'meta-service' }
      ]
    },
    options: {
      displayType: 'text',
      threshold: thresholds.default,
      valueFormat: 'human'
    }
  });

  await expect.poll(() => metaService.urls.length).toBeGreaterThan(0);
});

test.describe('with the Ping metric', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockMetrics(mockApi, 'Ping_1', metrics);
  });

  test.describe('as text', () => {
    test('colors the value and the thresholds', async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mountWidget(mount, 'text', thresholds.default);

      await expect(component.getByText('34%')).toHaveCSS(
        'color',
        colors.success
      );
      await expect(component.getByText('Warning: 65% - 70%')).toHaveCSS(
        'color',
        colors.warning
      );
      await expect(component.getByText('Critical: 85% - 90%')).toHaveCSS(
        'color',
        colors.critical
      );

      await expectThemedScreenshot(component, 'single-metric-text');
    });

    test('uses the default color without thresholds', async ({ mount }) => {
      const component = await mountWidget(mount, 'text', thresholds.disabled);

      await expect(component.getByText('34%')).toHaveCSS(
        'color',
        colors.default
      );
      await expect(component.getByText('Warning: 70%')).toHaveCount(0);
      await expect(component.getByText('Critical: 90%')).toHaveCount(0);
    });

    test('colors the value as warning', async ({ mount }) => {
      const component = await mountWidget(mount, 'text', thresholds.warning);

      await expect(component.getByText('34%')).toHaveCSS(
        'color',
        colors.warning
      );
    });

    test('colors the value as critical', async ({ mount }) => {
      const component = await mountWidget(mount, 'text', thresholds.critical);

      await expect(component.getByText('34%')).toHaveCSS(
        'color',
        colors.critical
      );
      await expect(component.getByText('Warning: 10%')).toBeVisible();
      await expect(component.getByText('Critical: 20%')).toBeVisible();
    });
  });

  test.describe('as a single bar', () => {
    test('colors the bar and explains the thresholds', async ({
      mount,
      page
    }) => {
      const component = await mountWidget(mount, 'bar', thresholds.default);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.success
      );
      await expect(component.getByTestId('34-bar-#88B922')).toBeVisible();
      for (const [line, text] of [
        ['warning-line-65', 'Warning threshold: 65%'],
        ['warning-line-70', 'Warning threshold: 70%'],
        ['critical-line-85', 'Critical threshold: 85%'],
        ['critical-line-90', 'Critical threshold: 90%']
      ] as const) {
        await tooltipOf(
          page,
          component.getByTestId(`${line}-tooltip`),
          `${text}. Value defined by Ping_1 metric`
        );
      }
    });

    test('uses the default color without thresholds', async ({ mount }) => {
      const component = await mountWidget(mount, 'bar', thresholds.disabled);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.default
      );
      await expect(component.getByTestId('34-bar-#131D5A')).toBeVisible();
      await expect(component.getByTestId('warning-line-70')).toHaveCount(0);
      await expect(component.getByTestId('critical-line-90')).toHaveCount(0);
    });

    test('colors the bar as warning', async ({ mount }) => {
      const component = await mountWidget(mount, 'bar', thresholds.warning);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.warning
      );
      await expect(component.getByTestId('34-bar-#FD9B27')).toBeVisible();
    });

    test('colors the bar as critical with custom thresholds', async ({
      mount,
      page
    }) => {
      const component = await mountWidget(mount, 'bar', thresholds.critical);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.critical
      );
      await expect(component.getByTestId('34-bar-#FF4A4A')).toBeVisible();
      await tooltipOf(
        page,
        component.getByTestId('warning-line-10-tooltip'),
        'Warning threshold: 10%. Custom value'
      );
      await tooltipOf(
        page,
        component.getByTestId('critical-line-20-tooltip'),
        'Critical threshold: 20%. Custom value'
      );
    });
  });

  test.describe('as a gauge', () => {
    test('colors the arc and explains the thresholds', async ({
      mount,
      page
    }) => {
      const component = await mountWidget(mount, 'gauge', thresholds.default);
      const thresholdArcs = component.getByTestId('5-arc');

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.success
      );
      await expect(component.getByTestId('34-arc')).toHaveAttribute(
        'fill',
        '#88B922'
      );
      await expect(thresholdArcs).toHaveCount(2);

      await thresholdArcs.nth(0).dispatchEvent('mouseover');
      for (const value of [65, 70]) {
        await expect(
          page.getByText(
            `Warning threshold: ${value}%. Value defined by Ping_1 metric`
          )
        ).toBeVisible();
      }
      await thresholdArcs.nth(0).dispatchEvent('mouseleave');
      await thresholdArcs.nth(1).dispatchEvent('mouseover');
      for (const value of [85, 90]) {
        await expect(
          page.getByText(
            `Critical threshold: ${value}%. Value defined by Ping_1 metric`
          )
        ).toBeVisible();
      }
    });

    test('uses the default color without thresholds', async ({ mount }) => {
      const component = await mountWidget(mount, 'gauge', thresholds.disabled);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.default
      );
      await expect(component.getByTestId('34-arc')).toHaveAttribute(
        'fill',
        '#131D5A'
      );
      await expect(component.getByTestId('5-arc')).toHaveCount(0);
    });

    test('colors the arc as warning', async ({ mount }) => {
      const component = await mountWidget(mount, 'gauge', thresholds.warning);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.warning
      );
      await expect(component.getByTestId('34-arc')).toHaveAttribute(
        'fill',
        '#FD9B27'
      );
    });

    test('colors the arcs with custom critical thresholds', async ({
      mount
    }) => {
      const component = await mountWidget(mount, 'gauge', thresholds.critical);

      await expect(component.getByText('34%')).toHaveCSS(
        'fill',
        colors.critical
      );
      await expect(component.getByTestId('34-arc').nth(1)).toHaveAttribute(
        'fill',
        '#FF4A4A'
      );
      await expect(component.getByTestId('24-arc')).toHaveAttribute(
        'fill',
        '#FD9B27'
      );
      await expect(component.getByTestId('14-arc')).toHaveAttribute(
        'fill',
        '#FF4A4A'
      );
    });
  });
});

const formats = [
  { expected: '332.06 KiB', valueFormat: 'human' },
  { expected: '340032.4232 B', valueFormat: 'raw' }
];

for (const displayType of ['text', 'bar', 'gauge']) {
  for (const { expected, valueFormat } of formats) {
    test(`formats a ${displayType} value as ${valueFormat}`, async ({
      mockApi,
      mount
    }) => {
      await mockMetrics(mockApi, 'Disk', metricWithBytUnit);
      const component = await mountWidget(
        mount,
        displayType,
        thresholds.default,
        {
          data: diskData,
          valueFormat
        }
      );

      await expect(component.getByText(expected)).toBeVisible();
    });
  }
}
