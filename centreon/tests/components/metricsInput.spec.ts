import type { Locator, Page } from '@playwright/test';

import serviceMetric from '../../cypress/fixtures/Dashboards/Dashboard/serviceMetric.json';
import serviceMetrics from '../../cypress/fixtures/Dashboards/Dashboard/serviceMetrics.json';
import tooManyServiceMetrics from '../../cypress/fixtures/Dashboards/Dashboard/tooManyServiceMetrics.json';
import {
  labelAvailable,
  labelIsTheSelectedResource,
  labelMetrics,
  labelSelectMetric,
  labelThresholdsAreAutomaticallyHidden,
  labelYouHaveTooManyMetrics
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import widgetDataProperties from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-data/properties.json';
import { expect, test } from './fixtures';

// Migrated from Dashboards/.../WidgetProperties/Inputs/Metrics/Metrics.
const storyId = 'Metrics/Metrics/Default';
const metricsEndpoint =
  './api/latest/monitoring/dashboard/metrics/performances';

const hostGroups = {
  resources: [
    { id: 1, name: 'Host group 1' },
    { id: 2, name: 'Host group 2' }
  ],
  resourceType: 'host-group'
};

const props = ({
  resources = [hostGroups],
  singleMetricSelection = true,
  singleResourceSelection = true
}: {
  resources?: Array<unknown>;
  singleMetricSelection?: boolean;
  singleResourceSelection?: boolean;
} = {}) => ({
  extraValues: { data: { metrics: [], resources } },
  widgetProperties: {
    ...widgetDataProperties,
    singleMetricSelection,
    singleResourceSelection
  }
});

const openSelector = async (component: Locator): Promise<void> => {
  await component.getByTestId(labelSelectMetric).click();
};
const option = (page: Page, testId: string): Locator =>
  page.getByTestId(testId);

test.describe('with a single resource per metric', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({ endpoint: metricsEndpoint, response: serviceMetrics });
  });

  test('lists and selects the retrieved metrics', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(storyId, props());

    await openSelector(component);
    await expect(page.getByText('rtmax (ms)')).toBeVisible();
    await expect(page.getByText('pl (%)')).toBeVisible();
    await page.getByText('pl (%)').click();

    await expect(component.getByTestId(labelSelectMetric)).toHaveValue(
      'pl (%)'
    );
    await expectThemedScreenshot(component, 'metrics-input');
  });

  test('names the selected resource of a metric with several resources', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, props());

    await openSelector(component);
    await page.getByText('pl (%)').click();

    await expect(
      component.getByText(`Centreon-1:Ping ${labelIsTheSelectedResource}`)
    ).toBeVisible();
  });

  test('counts the available metrics', async ({ mount }) => {
    const component = await mount(storyId, props());

    await expect(
      component.getByText(`${labelMetrics} (4 ${labelAvailable})`)
    ).toBeVisible();
  });

  test('disables the selector until the resources are filled', async ({
    mount
  }) => {
    const component = await mount(
      storyId,
      props({
        resources: [hostGroups, { resources: [], resourceType: 'service' }]
      })
    );

    await expect(component.getByTestId(labelSelectMetric)).toBeDisabled();
  });
});

test('stops at too many metrics', async ({ mockApi, mount }) => {
  await mockApi({ endpoint: metricsEndpoint, response: tooManyServiceMetrics });
  const component = await mount(storyId, props());

  await expect(
    component.getByText(`${labelMetrics} (1002 ${labelAvailable})`)
  ).toBeVisible();
  await expect(component.getByText(labelYouHaveTooManyMetrics)).toBeVisible();
  await expect(component.getByTestId(labelSelectMetric)).toBeDisabled();
});

test('selects a metric of a single resource', async ({
  mockApi,
  mount,
  page
}) => {
  await mockApi({ endpoint: metricsEndpoint, response: serviceMetric });
  const component = await mount(storyId, props());

  await openSelector(component);
  await page.getByText('pl (%)').click();

  await expect(component.getByTestId(labelSelectMetric)).toHaveValue('pl (%)');
});

const expandResources = async (page: Page): Promise<void> => {
  for (const metric of ['rtmax', 'pl']) {
    await option(page, `${metric}-summary`).click();
    const accordion = page.getByTestId(`${metric}-accordion`);
    await expect(accordion.getByText('Centreon-1:Ping')).toBeVisible();
    await expect(accordion.getByText('Centreon-2:Ping')).toBeVisible();
  }
};

for (const singleMetricSelection of [true, false]) {
  test.describe(`${singleMetricSelection ? 'single' : 'multiple'} metric selection with several resources`, () => {
    test.beforeEach(async ({ mockApi }) => {
      await mockApi({ endpoint: metricsEndpoint, response: serviceMetrics });
    });

    test('lists every metric with its resources', async ({ mount, page }) => {
      const component = await mount(
        storyId,
        props({ singleMetricSelection, singleResourceSelection: false })
      );

      await openSelector(component);
      for (const metric of ['rtmax', 'pl']) {
        await expect(option(page, metric)).toHaveAttribute(
          'data-checked',
          'false'
        );
      }
      await expandResources(page);
    });

    test('selects every resource of a selected metric', async ({
      mount,
      page
    }) => {
      const component = await mount(
        storyId,
        props({ singleMetricSelection, singleResourceSelection: false })
      );

      await openSelector(component);
      await option(page, 'rtmax').click();

      await expect(option(page, 'rtmax')).toHaveAttribute(
        'data-checked',
        'true'
      );
      await expect(option(page, 'pl')).toHaveAttribute('data-checked', 'false');
      await expect(component.getByText('rtmax (ms)/2')).toBeVisible();
      if (!singleMetricSelection) {
        await option(page, 'rtmax-summary').click();
        for (const resource of ['Centreon-1:Ping', 'Centreon-2:Ping']) {
          await expect(option(page, `rtmax_${resource}`)).toHaveAttribute(
            'data-checked',
            'true'
          );
        }
      }
    });
  });
}

test.describe('multiple metric selection, resource by resource', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({ endpoint: metricsEndpoint, response: serviceMetrics });
  });

  const multiple = props({
    singleMetricSelection: false,
    singleResourceSelection: false
  });
  const indeterminate = (page: Page): Locator =>
    option(page, 'pl').locator('input');

  test('excludes an unchecked resource', async ({ mount, page }) => {
    const component = await mount(storyId, multiple);

    await openSelector(component);
    await option(page, 'pl').click();
    await option(page, 'pl-summary').click();
    await option(page, 'pl_Centreon-1:Ping').click();

    await expect(option(page, 'pl_Centreon-1:Ping')).toHaveAttribute(
      'data-checked',
      'false'
    );
    await expect(option(page, 'pl_Centreon-2:Ping')).toHaveAttribute(
      'data-checked',
      'true'
    );
    await expect(component.getByText('pl (%)/1')).toBeVisible();
    await expect(indeterminate(page)).toHaveAttribute(
      'data-indeterminate',
      'true'
    );
  });

  test('selects a single resource', async ({ mount, page }) => {
    const component = await mount(storyId, multiple);

    await openSelector(component);
    await option(page, 'pl-summary').click();
    await option(page, 'pl_Centreon-1:Ping').click();

    await expect(option(page, 'pl_Centreon-1:Ping')).toHaveAttribute(
      'data-checked',
      'true'
    );
    await expect(option(page, 'pl_Centreon-2:Ping')).toHaveAttribute(
      'data-checked',
      'false'
    );
    await expect(component.getByText('pl (%)/1')).toBeVisible();
    await expect(indeterminate(page)).toHaveAttribute(
      'data-indeterminate',
      'true'
    );
  });

  test('checks the metric once all its resources are selected', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, multiple);

    await openSelector(component);
    await option(page, 'pl-summary').click();
    await option(page, 'pl_Centreon-1:Ping').click();
    await option(page, 'pl_Centreon-2:Ping').click();

    await expect(component.getByText('pl (%)/2')).toBeVisible();
    await expect(indeterminate(page)).toHaveAttribute(
      'data-indeterminate',
      'false'
    );
    await expect(option(page, 'pl')).toHaveAttribute('data-checked', 'true');
  });

  test('warns that thresholds are hidden for several units', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId, multiple);

    await openSelector(component);
    await option(page, 'rtmax').click();
    await option(page, 'pl').click();

    await expect(
      component.getByText(labelThresholdsAreAutomaticallyHidden)
    ).toBeAttached();
  });
});
