import { randomUUID } from 'node:crypto';

import type { Page } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import {
  criticalServiceNames,
  okServiceName,
  resourcesHostName
} from '../../fixtures/resources';
import { expect, test } from '../../fixtures/test';
import { ensureResourcesMonitored } from '../../helpers/provisioning';
import { DashboardDetailPage } from '../../pages/DashboardDetailPage';

/**
 * Replaces the real-data parts of Dashboards/08-18: each status widget,
 * configured through the API on the monitored fixture, shows the states the
 * engine reports. Their display options are covered by the component suite.
 */
const description = {
  content:
    '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1}],"direction":null,"format":"","indent":0,"type":"root","version":1}}',
  enabled: false
};

const fixtureHost = (hostId: number) => ({
  resources: [
    {
      resources: [{ id: hostId, name: resourcesHostName }],
      resourceType: 'host'
    }
  ]
});

interface WidgetCase {
  /** What the widget must show of the fixture. */
  expectData: (page: Page) => Promise<void>;
  label: string;
  name: string;
  path: string;
  settings: (hostId: number) => object;
}

const widgets: Array<WidgetCase> = [
  {
    expectData: async (page) => {
      for (const service of criticalServiceNames) {
        await expect(page.getByText(service).first()).toBeVisible();
      }
    },
    label: 'status grid',
    name: 'centreon-widget-statusgrid',
    path: '/widgets/statusgrid',
    settings: (hostId) => ({
      data: fixtureHost(hostId),
      options: {
        description,
        name: 'Status grid',
        refreshInterval: 'default',
        resourceType: 'service',
        sortBy: 'status_severity_code',
        states: [],
        statuses: ['success', 'warning', 'problem', 'undefined', 'pending'],
        tiles: 100,
        viewMode: 'standard'
      }
    })
  },
  {
    expectData: async (page) => {
      for (const service of [...criticalServiceNames, okServiceName]) {
        await expect(page.getByText(service, { exact: true })).toBeVisible();
      }
    },
    label: 'resource table',
    name: 'centreon-widget-resourcestable',
    path: '/widgets/resourcestable',
    settings: (hostId) => ({
      data: fixtureHost(hostId),
      options: {
        description,
        displayType: 'all',
        name: 'Resource table',
        refreshInterval: 'default',
        selectedColumnIds: ['status', 'resource', 'parent_resource'],
        states: [],
        statuses: []
      }
    })
  },
  {
    expectData: async (page) => {
      // Three CRITICAL services and one OK on the host.
      await expect(page.getByTestId('Title')).toHaveText('services');
      await expect(page.getByText('4', { exact: true }).first()).toBeVisible();
      await expect(page.getByText('3', { exact: true }).first()).toBeVisible();
    },
    label: 'status chart',
    name: 'centreon-widget-statuschart',
    path: '/widgets/statuschart',
    settings: (hostId) => ({
      data: fixtureHost(hostId),
      options: {
        description,
        displayLegend: true,
        displayType: 'donut',
        displayValues: true,
        name: 'Status chart',
        refreshInterval: 'default',
        resourceTypes: ['service'],
        unit: 'number'
      }
    })
  }
];

test.use({ storageState: adminStorageStatePath });

test.beforeAll(async ({ baseURL }) => {
  test.setTimeout(300_000);
  await ensureResourcesMonitored(baseURL ?? '');
});

for (const widget of widgets) {
  test(`shows the monitored states in a ${widget.label} widget`, async ({
    adminApi,
    page
  }) => {
    const { parentId } = await adminApi.getServiceResource(
      criticalServiceNames[0]
    );
    const id = await adminApi.createDashboard({
      description: null,
      name: `pw-widget-${randomUUID().slice(0, 6)}`,
      panels: [],
      refresh: { interval: null, type: 'global' }
    });

    try {
      await adminApi.setDashboardWidget(id, {
        name: widget.name,
        path: widget.path,
        settings: widget.settings(parentId ?? 0)
      });
      await page.goto(`/centreon/home/dashboards/library/${id}`);
      await new DashboardDetailPage(page).expectOnDetailPage();
      await widget.expectData(page);
    } finally {
      await adminApi
        .v2('DELETE', `/configuration/dashboards/${id}`)
        .catch(() => undefined);
    }
  });
}
