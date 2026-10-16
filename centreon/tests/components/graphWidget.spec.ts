import type { Locator, Page } from '@playwright/test';

import lineChartForScrollableLegend from '../../cypress/fixtures/Widgets/Graph/legend/lineChartForScrollableLegend.json';
import lineChartWithRedundantHostAndServiceName from '../../cypress/fixtures/Widgets/Graph/legend/lineChartWithRedundantHostAndServiceName.json';
import lineChartWithRedundantHostName from '../../cypress/fixtures/Widgets/Graph/legend/lineChartWithRedundantHostName.json';
import lineChartWithRedundantServiceName from '../../cypress/fixtures/Widgets/Graph/legend/lineChartWithRedundantServiceName.json';
import serviceMetricsForScrollableLegend from '../../cypress/fixtures/Widgets/Graph/legend/serviceMetricsForScrollableLegend.json';
import lineChart from '../../cypress/fixtures/Widgets/Graph/lineChart.json';
import { labelPreviewRemainsEmpty } from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-graph/src/Graph. The
// threshold tooltips are asserted interpolated (Cypress checked {{metric}}).
const storyId = 'centreon-widget-graph/src/LineChart/Default';
const graphEndpoint =
  './api/latest/monitoring/dashboard/metrics/performances/data';

const serviceMetrics = {
  metrics: [
    { id: 1, name: 'cpu', unit: '%' },
    { id: 2, name: 'cpu AVG', unit: '%' }
  ],
  resources: [
    { resources: [{ id: 1, name: 'HG1' }], resourceType: 'host-group' }
  ]
};

const threshold = (extra: Record<string, unknown> = {}) => ({
  criticalType: 'default',
  customCritical: 0,
  customWarning: 0,
  enabled: true,
  warningType: 'default',
  ...extra
});

const options = (extra: Record<string, unknown> = {}) => ({
  threshold: threshold(),
  timeperiod: { timePeriodType: 1 },
  ...extra
});

test.use({ viewport: { height: 800, width: 1280 } });

const expectLegendHeader = async (component: Locator): Promise<void> => {
  for (const name of ['cpu', 'cpu: avg']) {
    const legend = component.getByLabel(name, { exact: true });
    await expect(legend.getByText(name, { exact: true })).toBeVisible();
    await expect(legend.getByText('%')).toBeVisible();
  }
};

const tooltipOf = async (
  page: Page,
  component: Locator,
  line: string,
  text: string
) => {
  await component.getByTestId(`${line}-tooltip`).dispatchEvent('mouseover');
  await expect(page.getByText(text).first()).toBeAttached();
};

test('reads a public widget from the public API', async ({
  mockApi,
  mount
}) => {
  const publicWidget = await mockApi({
    endpoint:
      './api/latest/it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1',
    response: lineChart
  });

  await mount(storyId, {
    data: serviceMetrics,
    isPublic: true,
    options: options()
  });

  await expect.poll(() => publicWidget.urls.length).toBeGreaterThan(0);
});

test('asks for metrics when none is selected', async ({ mount }) => {
  const component = await mount(storyId, {
    data: { metrics: [], resources: [] },
    options: options()
  });

  await expect(component.getByText(labelPreviewRemainsEmpty)).toBeVisible();
});

test.describe('with the CPU metrics', () => {
  let graph: { urls: Array<URL> };

  test.beforeEach(async ({ mockApi }) => {
    graph = await mockApi({ endpoint: graphEndpoint, response: lineChart });
  });

  test('draws the line chart with its default thresholds', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(storyId, {
      data: serviceMetrics,
      options: options()
    });

    await expectLegendHeader(component);
    const search = graph.urls[0]?.search ?? '';
    expect(search).toContain('metric_names[]=cpu');
    expect(search).toContain('metric_names[]=cpu%20AVG');
    expect(search).toContain(
      'search=%7B%22%24and%22%3A%5B%7B%22hostgroup.id%22%3A%7B%22%24in%22%3A%5B1%5D%7D%7D%5D%7D'
    );

    for (const [line, text] of [
      ['warning-line-65', 'Warning threshold: 65%'],
      ['warning-line-70', 'Warning threshold: 70%'],
      ['critical-line-85', 'Critical threshold: 85%'],
      ['critical-line-90', 'Critical threshold: 90%']
    ] as const) {
      await expect(component.getByTestId(line)).toBeVisible();
      await tooltipOf(page, component, line, `${text}. Value defined by`);
    }

    await expectThemedScreenshot(component, 'graph');
  });

  test('draws no threshold when disabled', async ({ mount }) => {
    const component = await mount(storyId, {
      data: serviceMetrics,
      options: options({ threshold: threshold({ enabled: false }) })
    });

    await expectLegendHeader(component);
    for (const line of [
      'warning-line-65',
      'warning-line-70',
      'critical-line-85',
      'critical-line-90'
    ]) {
      await expect(component.getByTestId(line)).toHaveCount(0);
    }
  });

  test('draws a custom warning threshold', async ({ mount, page }) => {
    const component = await mount(storyId, {
      data: serviceMetrics,
      options: options({
        threshold: threshold({ customWarning: 20, warningType: 'custom' })
      })
    });

    await expect(component.getByTestId('warning-line-20')).toBeVisible();
    await tooltipOf(
      page,
      component,
      'warning-line-20',
      'Warning threshold: 20%. Custom value'
    );
  });

  test('draws custom warning and critical thresholds', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, {
      data: serviceMetrics,
      options: options({
        threshold: threshold({
          criticalType: 'custom',
          customCritical: 20,
          customWarning: 10,
          warningType: 'custom'
        })
      })
    });

    await expect(component.getByTestId('warning-line-10')).toBeVisible();
    await expect(component.getByTestId('critical-line-20')).toBeVisible();
    await tooltipOf(
      page,
      component,
      'critical-line-20',
      'Critical threshold: 20%. Custom value'
    );
  });

  test('requests a custom time period', async ({ mount }) => {
    await mount(storyId, {
      data: serviceMetrics,
      options: options({
        timeperiod: {
          end: '2021-09-02T00:00:00.000Z',
          start: '2021-09-01T00:00:00.000Z',
          timePeriodType: -1
        }
      })
    });

    await expect
      .poll(() => graph.urls[0]?.search)
      .toContain('start=2021-09-01T00:00:00.000Z');
    expect(graph.urls[0]?.search).toContain('end=2021-09-02T00:00:00.000Z');
  });

  const styles = [
    { curveType: 'natural' },
    { curveType: 'step' },
    {
      areaOpacity: 10,
      curveType: 'natural',
      dashLength: 6,
      dashOffset: 10,
      gridLinesType: 'horizontal',
      isCenteredZero: true,
      legendDisplayMode: 'list',
      legendPlacement: 'right',
      lineStyleMode: 'dash',
      lineWidth: 1,
      lineWidthMode: 'custom',
      showArea: 'show',
      showAxisBorder: false,
      showGridLines: true,
      showLegend: true,
      showPoints: true
    }
  ];

  for (const style of styles) {
    test(`draws a line chart styled ${JSON.stringify(style).slice(0, 40)}`, async ({
      mount
    }) => {
      const component = await mount(storyId, {
        data: serviceMetrics,
        options: options(style)
      });

      await expectLegendHeader(component);
    });
  }

  const bars = [
    { displayType: 'bar' },
    { barOpacity: 20, barRadius: 50, displayType: 'bar' },
    { displayType: 'bar-stacked' },
    { barOpacity: 20, displayType: 'bar-stacked' }
  ];

  for (const bar of bars) {
    test(`draws a ${JSON.stringify(bar)} chart`, async ({ mount }) => {
      const component = await mount(storyId, {
        data: serviceMetrics,
        options: options(bar)
      });

      await expectLegendHeader(component);
      await expect(component.getByTestId('stacked-bar-1-0-40')).toHaveAttribute(
        'opacity',
        /.+/
      );
    });
  }

  test('searches a meta service', async ({ mount }) => {
    await mount(storyId, {
      data: {
        metrics: [{ id: 1, name: 'free', unit: '' }],
        resources: [
          { resources: [{ id: 1, name: 'M1' }], resourceType: 'meta-service' }
        ]
      },
      options: options()
    });

    await expect
      .poll(() => graph.urls[0]?.searchParams.get('search'))
      .toBe('{"$and":[{"metaservice.id":{"$in":[1]}}]}');
    expect(graph.urls[0]?.searchParams.get('metrics_names')).toBeNull();
  });
});

for (const legendDisplayMode of ['list', 'grid']) {
  for (const legendPlacement of ['left', 'bottom', 'right']) {
    test(`scrolls a ${legendDisplayMode} legend placed ${legendPlacement}`, async ({
      mockApi,
      mount
    }) => {
      await mockApi({
        endpoint: graphEndpoint,
        response: lineChartForScrollableLegend
      });
      const component = await mount(storyId, {
        data: serviceMetricsForScrollableLegend,
        options: options({
          legendDisplayMode,
          legendPlacement,
          showLegend: true
        })
      });
      const legend = component.locator('[data-legend="true"]');

      await expect(component.locator('path')).toHaveCount(100);
      await expect(legend).toHaveCSS('overflow-y', 'auto');
      await expect(legend).toHaveCSS(
        'overflow-x',
        legendPlacement === 'bottom' ? 'auto' : 'hidden'
      );
      await expect(
        component.getByText('Legend 1 Centreon-Server')
      ).toBeAttached();
      await legend.evaluate((element) =>
        element.scrollTo(0, element.scrollHeight)
      );
      await expect(
        component.getByText('Legend 99 Centreon-Server')
      ).toBeAttached();
    });
  }
}

const redundantNames = [
  {
    fixture: lineChartWithRedundantHostName,
    hidden: ['host_name'],
    name: 'host'
  },
  {
    fixture: lineChartWithRedundantServiceName,
    hidden: ['service_name'],
    name: 'service'
  },
  {
    fixture: lineChartWithRedundantHostAndServiceName,
    hidden: ['host_name', 'service_name'],
    name: 'host and service'
  }
];

for (const { fixture, hidden, name } of redundantNames) {
  test(`hides the redundant ${name} name in the legend and tooltip`, async ({
    mockApi,
    mount,
    page
  }) => {
    await mockApi({ endpoint: graphEndpoint, response: fixture });
    const component = await mount(storyId, {
      data: serviceMetrics,
      options: options({ showLegend: true })
    });
    const legend = component.locator('[data-legend="true"]');

    // The chart overlay catches the pointer: move it there like realHover did.
    await component
      .locator('path[data-metric="1"]')
      .first()
      .hover({ force: true });
    const tooltip = page.getByRole('tooltip');

    for (const metric of fixture.metrics as unknown as Array<
      Record<string, string>
    >) {
      for (const field of hidden) {
        await expect(legend).not.toContainText(metric[field] as string);
        await expect(tooltip).not.toContainText(metric[field] as string);
      }
      await expect(legend).toContainText(metric.metric as string);
      await expect(tooltip).toContainText(metric.metric as string);
    }
  });
}
