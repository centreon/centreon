import { randomUUID } from 'node:crypto';

import type { Page } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { queryCentreonDb } from '../../helpers/docker';
import { waitForServiceMetrics } from '../../helpers/metrics';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';

/**
 * Replaces the real-data parts of Dashboards/09-11: the metric widgets show
 * the performance data of the Ping service of the central server. Their
 * display options are covered by the component suite.
 */
const host = 'Centreon-Server';
const service = 'Ping';

interface Metric {
  hostId: number;
  id: number;
  name: string;
  serviceId: number;
  unit: string;
}

const metric = (name: string): Metric => {
  const [hostId, serviceId, id, unit] = queryCentreonDb(
    `SELECT i.host_id, i.service_id, m.metric_id, IFNULL(m.unit_name, '') FROM index_data i JOIN metrics m ON m.index_id = i.id WHERE i.host_name = '${host}' AND i.service_description = '${service}' AND m.metric_name = '${name}'`,
    'centreon_storage'
  )
    .trim()
    .split('\t');

  return {
    hostId: Number(hostId),
    id: Number(id),
    name,
    serviceId: Number(serviceId),
    unit
  };
};

const description = {
  content:
    '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1}],"direction":null,"format":"","indent":0,"type":"root","version":1}}',
  enabled: false
};

const threshold = {
  baseColor: null,
  criticalType: 'default',
  customCritical: null,
  customWarning: null,
  enabled: true,
  warningType: 'default'
};

const metricSettings = ({ id, name, unit }: Metric) => ({
  criticalHighThreshold: 400,
  criticalLowThreshold: 0,
  excludedMetrics: [],
  id,
  includeAllMetrics: true,
  name,
  unit,
  warningHighThreshold: 200,
  warningLowThreshold: 0
});

const hostResource = ({ hostId }: Metric) => ({
  resources: [{ id: hostId, name: host }],
  resourceType: 'host'
});

const serviceData = ({ hostId, serviceId }: Metric) => [
  {
    id: serviceId,
    name: service,
    parentName: host,
    uuid: `h${hostId}-s${serviceId}`
  }
];

interface WidgetCase {
  expectData: (page: Page, rta: Metric) => Promise<void>;
  label: string;
  name: string;
  path: string;
  settings: (rta: Metric, pl: Metric) => object;
}

const widgets: Array<WidgetCase> = [
  {
    expectData: async (page) => {
      await expect(page.getByText(/^\d+(\.\d+)? ms$/).first()).toBeVisible();
      await expect(page.getByText('Warning: 200 ms')).toBeVisible();
      await expect(page.getByText('Critical: 400 ms')).toBeVisible();
    },
    label: 'single metric',
    name: 'centreon-widget-singlemetric',
    path: '/widgets/singlemetric',
    settings: (rta) => ({
      data: {
        metrics: [
          {
            ...metricSettings(rta),
            serviceId: rta.serviceId,
            serviceName: service
          }
        ],
        resources: [
          hostResource(rta),
          {
            resources: [{ id: service, name: service }],
            resourceType: 'service'
          }
        ],
        services: serviceData(rta)
      },
      options: {
        description,
        displayType: 'text',
        refreshInterval: 'default',
        threshold,
        valueFormat: 'human'
      }
    })
  },
  {
    expectData: async (page) => {
      const entry = page.getByText(`#1 ${host}_${service}`);
      await expect(entry).toBeVisible();
      await expect(page.getByText(/^\d+(\.\d+)? ms$/).first()).toBeVisible();
      await test.step('An entry opens its resource in Resources Status', async () => {
        const [target] = await Promise.all([
          page.context().waitForEvent('page'),
          entry.click()
        ]);
        await expect(target).toHaveURL(/monitoring\/resources/);
        await expect(target.getByText(service, { exact: true }).first()).toBeVisible();
      });
    },
    label: 'top/bottom',
    name: 'centreon-widget-topbottom',
    path: '/widgets/topbottom',
    settings: (rta) => ({
      data: { metrics: [metricSettings(rta)], resources: [hostResource(rta)] },
      options: {
        description,
        refreshInterval: 'default',
        threshold,
        topBottomSettings: {
          numberOfValues: 10,
          order: 'top',
          showLabels: true
        },
        valueFormat: 'human'
      }
    })
  },
  {
    expectData: async (page) => {
      await expect(page.getByText('rta', { exact: true })).toBeVisible();
      await expect(page.getByText('pl', { exact: true })).toBeVisible();
      // The rta curve has points: its average is computed.
      await expect(page.getByText(/^Avg: \d+(\.\d+)? ?ms$/)).toBeVisible();
    },
    label: 'metrics graph',
    name: 'centreon-widget-graph',
    path: '/widgets/graph',
    settings: (rta, pl) => ({
      data: {
        metrics: [metricSettings(rta), metricSettings(pl)],
        resources: [hostResource(rta)],
        services: serviceData(rta)
      },
      options: {
        curveType: 'linear',
        description,
        isCenteredZero: false,
        lineStyleMode: 'solid',
        lineWidthMode: 'auto',
        refreshInterval: 'default',
        scale: 'linear',
        showArea: 'auto',
        showAxisBorder: true,
        showGridLines: true,
        showLegend: true,
        showPoints: false,
        threshold: { ...threshold, enabled: false },
        timeperiod: { end: null, start: null, timePeriodType: 1 },
        tooltipMode: 'all',
        tooltipSortOrder: 'name',
        yAxisTickLabelRotation: 0
      }
    })
  }
];

test.use({ storageState: adminStorageStatePath });

test.beforeAll(async () => {
  // A fresh stack gets the metrics, then their points, with the first checks.
  test.setTimeout(360_000);
  await waitForServiceMetrics({ metrics: ['rta', 'pl'], points: 3, service });
});

for (const widget of widgets) {
  test(`shows the performance data in a ${widget.label} widget`, async ({
    adminApi,
    page
  }) => {
    const rta = metric('rta');
    const pl = metric('pl');
    const id = await adminApi.createDashboard({
      description: null,
      name: `pw-metric-widget-${randomUUID().slice(0, 6)}`,
      panels: [],
      refresh: { interval: null, type: 'global' }
    });

    try {
      await adminApi.setDashboardWidget(id, {
        name: widget.name,
        path: widget.path,
        settings: widget.settings(rta, pl)
      });
      await page.goto(`/centreon/home/dashboards/library/${id}`);
      await new DashboardDetailPage(page).expectOnDetailPage();
      await widget.expectData(page, rta);
    } finally {
      await adminApi
        .v2('DELETE', `/configuration/dashboards/${id}`)
        .catch(() => undefined);
    }
  });
}
