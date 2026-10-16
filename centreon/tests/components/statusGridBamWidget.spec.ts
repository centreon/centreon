import type { Locator, Page } from '@playwright/test';

import bamCondensed from '../../cypress/fixtures/Widgets/StatusGrid/bamCondensed.json';
import booleanRuleDetails from '../../cypress/fixtures/Widgets/StatusGrid/booleanRuleDetails.json';
import businessActivities from '../../cypress/fixtures/Widgets/StatusGrid/businessActivities.json';
import criticalImpactBA from '../../cypress/fixtures/Widgets/StatusGrid/criticalImpactBA.json';
import criticalRatioBA from '../../cypress/fixtures/Widgets/StatusGrid/criticalRatioBA.json';
import impactBA from '../../cypress/fixtures/Widgets/StatusGrid/impactBA.json';
import indicators from '../../cypress/fixtures/Widgets/StatusGrid/indicators.json';
import ratioBA from '../../cypress/fixtures/Widgets/StatusGrid/ratioBA.json';
import worstStatusBA from '../../cypress/fixtures/Widgets/StatusGrid/worstStatusBA.json';
import {
  baCondensedOptions,
  baOptions,
  baResources,
  bvResources
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/centreon-widget-statusgrid/src/spec/testUtils';
import { expect, type MockApi, type MockedEndpoint, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-statusgrid/src/spec/StatusGridWithBam.
const storyId = 'centreon-widget-statusgrid/src/StatusGrid/Default';
const businessActivitiesEndpoint = 'bam/monitoring/business-activities';
const indicatorsEndpoint = 'bam/monitoring/business-activities/indicators';
const publicWidgetEndpoint =
  'it-edition-extensions/monitoring/dashboards/playlists/hash/dashboards/1/widgets/1';

test.use({ viewport: { height: 600, width: 1100 } });

const fixClock = (page: Page) =>
  page.clock.setFixedTime(new Date('2021-02-01T00:00:00Z'));
const tileOf = (marker: Locator) => marker.locator('xpath=../..');
const hover = (locator: Locator) => locator.dispatchEvent('mouseover');
const tooltip = (page: Page) => page.getByRole('tooltip');
const mountBam = (
  mount: (id: string, props?: object) => Promise<Locator>,
  props: object
) => mount(storyId, { withBam: true, ...props });

const mockBusinessActivities = async (
  mockApi: MockApi,
  { details = worstStatusBA as unknown, id = 1 } = {}
): Promise<Record<'listing' | 'public', MockedEndpoint>> => {
  await mockApi({
    endpoint: `${businessActivitiesEndpoint}/${id}`,
    response: details
  });

  return {
    listing: await mockApi({
      endpoint: businessActivitiesEndpoint,
      response: businessActivities
    }),
    public: await mockApi({
      endpoint: publicWidgetEndpoint,
      response: businessActivities
    })
  };
};

const mockStatuses = async (
  mockApi: MockApi,
  type: 'business activities' | 'KPIS'
): Promise<Record<'activities' | 'indicators' | 'public', MockedEndpoint>> => {
  await mockApi({
    // Slow, so the loading skeleton shows.
    delay: 2000,
    endpoint:
      type === 'business activities'
        ? `${businessActivitiesEndpoint}/statuses`
        : `${indicatorsEndpoint}/statuses`,
    response: bamCondensed
  });

  return {
    activities: await mockApi({
      endpoint: businessActivitiesEndpoint,
      response: businessActivities
    }),
    indicators: await mockApi({
      endpoint: indicatorsEndpoint,
      response: indicators
    }),
    public: await mockApi({
      endpoint: publicWidgetEndpoint,
      response: bamCondensed
    })
  };
};

const expectTile = async (
  component: Locator,
  name: string,
  status: string,
  color: string
) => {
  await expect(component.getByText(name, { exact: true })).toBeVisible();
  const marker = component.locator(`[data-status="${status}"]`).first();
  await expect(marker).toBeVisible();
  await expect(tileOf(marker)).toHaveCSS('background-color', color);
};

test.describe('public widget', () => {
  test('reads the standard view from the public API', async ({
    mockApi,
    mount
  }) => {
    const mocks = await mockBusinessActivities(mockApi);
    await mountBam(mount, {
      data: { resources: bvResources },
      isPublic: true,
      options: baOptions
    });

    await expect.poll(() => mocks.public.urls.length).toBeGreaterThan(0);
  });

  test('reads the condensed view from the public API', async ({
    mockApi,
    mount
  }) => {
    const mocks = await mockStatuses(mockApi, 'business activities');
    await mountBam(mount, {
      data: { resources: bvResources },
      isPublic: true,
      options: baCondensedOptions
    });

    await expect.poll(() => mocks.public.urls.length).toBeGreaterThan(0);
  });
});

test.describe('business activities', () => {
  test('displays a tile per status', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await fixClock(page);
    await mockBusinessActivities(mockApi);
    const component = await mountBam(mount, {
      data: { resources: bvResources },
      options: baOptions
    });

    await expectTile(component, 'ba1', 'ok', 'rgb(159, 199, 78)');
    await expectTile(component, 'ba2', 'pending', 'rgb(30, 190, 179)');
    await expectTile(component, 'ba3', 'unknown', 'rgb(227, 227, 227)');
    await expectThemedScreenshot(component, 'status-grid-business-activities');
  });

  const okColor = 'rgb(159, 199, 78)';
  const criticalColor = 'rgb(255, 110, 110)';
  const cases = [
    {
      color: okColor,
      details: worstStatusBA,
      id: 1,
      method: 'Worst Status',
      status: 'ok',
      texts: [
        'Calculation method : Worst Status',
        'All KPIs on this Business Activity are working fine.'
      ]
    },
    {
      color: okColor,
      details: impactBA,
      id: 1,
      method: 'Impact',
      status: 'ok',
      texts: [
        'Calculation method : Impact',
        'State information',
        'Health',
        '100%',
        'Warning threshold',
        '80%',
        'Critical threshold',
        '70%',
        'All KPIs on this Business Activity are working fine.'
      ]
    },
    {
      color: okColor,
      details: ratioBA,
      id: 1,
      method: 'Ratio',
      status: 'ok',
      texts: [
        'Calculation method : Ratio',
        'State information',
        'Critical KPIs',
        '0%',
        'Warning threshold',
        '75%',
        'Critical threshold',
        '80%',
        'All KPIs on this Business Activity are working fine.'
      ]
    },
    {
      color: criticalColor,
      details: criticalImpactBA,
      id: 4,
      method: 'Impact',
      status: 'critical',
      texts: [
        'Calculation method : Impact',
        'State information',
        'Health',
        '70%',
        'Warning threshold',
        '80%',
        'Critical threshold',
        'Ping',
        '12%',
        '15%',
        'boolean 2',
        '75%',
        '100%',
        '1/3 KPIs are working fine.'
      ]
    },
    {
      color: criticalColor,
      details: criticalRatioBA,
      id: 5,
      method: 'Ratio',
      status: 'critical',
      texts: [
        'Calculation method : Ratio',
        'State information',
        'Critical KPIs',
        '0%',
        'Warning threshold',
        '75%',
        'Critical threshold',
        '80%',
        'ba1',
        '1/3 KPIs are working fine.'
      ]
    }
  ];

  for (const { color, details, id, method, status, texts } of cases) {
    test(`describes a ${method} ${status} business activity in a tooltip`, async ({
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      await mockBusinessActivities(mockApi, { details, id });
      const component = await mountBam(mount, {
        data: { resources: bvResources },
        options: baOptions
      });
      await hover(component.getByText(`ba${id}`, { exact: true }));

      await expect(page.locator(`[data-resourceName="ba${id}"]`)).toHaveCSS(
        'color',
        color
      );
      for (const text of texts) {
        await expect(tooltip(page).getByText(text).first()).toBeVisible();
      }
    });
  }
});

test.describe('indicators', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({ endpoint: indicatorsEndpoint, response: indicators });
    await mockApi({
      endpoint: 'bam/monitoring/indicators/boolean-rules/1',
      response: booleanRuleDetails
    });
  });

  test('displays a tile per status', async ({ mount, page }) => {
    await fixClock(page);
    const component = await mountBam(mount, {
      data: { resources: baResources },
      options: baOptions
    });

    await expectTile(component, 'Ping', 'ok', 'rgb(159, 199, 78)');
    await expectTile(component, 'Memory', 'unknown', 'rgb(227, 227, 227)');
  });

  test('describes a boolean rule in a tooltip', async ({ mount, page }) => {
    await fixClock(page);
    const component = await mountBam(mount, {
      data: { resources: baResources },
      options: baOptions
    });
    await hover(component.getByText('boolean 1', { exact: true }));

    await expect(page.locator('[data-resourceName="boolean 1"]')).toHaveCSS(
      'color',
      'rgb(159, 199, 78)'
    );
    for (const text of [
      'Parent: ba1',
      'Impact applied when: true',
      'Expression in ok.'
    ]) {
      await expect(tooltip(page).getByText(text)).toBeVisible();
    }
    await expect(
      tooltip(page).getByText('Click here for details')
    ).toHaveAttribute('href', '/main.php?p=62611&o=c&boolean_id=1');
  });
});

test.describe('condensed view', () => {
  const expectStatusTiles = async (component: Locator, total: string) => {
    for (const text of [total, 'ok', '22', 'critical', '10', 'pending', '4']) {
      await expect(
        component.getByText(text, { exact: true }).first()
      ).toBeVisible();
    }
  };
  const tooltipSearch = (mock: MockedEndpoint) => () =>
    mock.urls.at(-1)?.searchParams.get('search');

  test.describe('business activities', () => {
    const props = {
      data: { resources: bvResources },
      options: baCondensedOptions
    };

    test('shows a skeleton, then the status tiles linked to the BA page', async ({
      expectThemedScreenshot,
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      await mockStatuses(mockApi, 'business activities');
      const component = await mountBam(mount, props);

      await expect(
        component.locator('[data-skeleton="true"]').first()
      ).toBeVisible();
      await expectStatusTiles(component, '40 business activities');
      for (const status of ['ok', 'critical']) {
        await expect(
          component.locator(`[data-label="${status}"]`)
        ).toHaveAttribute('href', `/main.php?p=20701&status=${status}`);
      }
      await expectThemedScreenshot(component, 'status-grid-bam-condensed');
    });

    test('describes each status in a tooltip', async ({
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      const mocks = await mockStatuses(mockApi, 'business activities');
      const component = await mountBam(mount, props);
      await expectStatusTiles(component, '40 business activities');

      await hover(component.locator('[data-label="ok"]'));
      await expect(page.getByText('February 1, 2021').first()).toBeVisible();
      await expect(
        page.getByText('22/40 business activities are working fine.')
      ).toBeVisible();

      for (const [status, count] of [
        ['critical', '10'],
        ['pending', '4']
      ]) {
        await hover(component.locator(`[data-label="${status}"]`));
        await expect
          .poll(tooltipSearch(mocks.activities))
          .toBe(
            `{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"status":{"0":"${status}"}}]}]}`
          );
        await expect(
          tooltip(page).getByText(
            `Status: ${status[0].toUpperCase()}${status.slice(1)}`
          )
        ).toBeVisible();
        await expect(
          tooltip(page).getByText(`${count} business activities`)
        ).toBeVisible();
        for (const name of ['ba1', 'ba2', 'ba3']) {
          await expect(
            tooltip(page).getByText(name, { exact: true }).first()
          ).toBeVisible();
        }
      }
    });
  });

  test.describe('KPIs', () => {
    const props = {
      data: { resources: baResources },
      options: baCondensedOptions
    };

    test('shows a skeleton, then the status tiles linked to the indicators page', async ({
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      await mockStatuses(mockApi, 'KPIS');
      const component = await mountBam(mount, props);

      await expect(
        component.locator('[data-skeleton="true"]').first()
      ).toBeVisible();
      await expectStatusTiles(component, '40 KPIS');
      await expect(component.locator('[data-label="ok"]')).toHaveAttribute(
        'href',
        '/main.php?p=62606'
      );
    });

    test('describes each status in a tooltip', async ({
      mockApi,
      mount,
      page
    }) => {
      await fixClock(page);
      const mocks = await mockStatuses(mockApi, 'KPIS');
      const component = await mountBam(mount, props);
      await expectStatusTiles(component, '40 KPIS');

      await hover(component.locator('[data-label="ok"]'));
      await expect(page.getByText('February 1, 2021').first()).toBeVisible();
      await expect(
        page.getByText('22/40 KPIS are working fine.')
      ).toBeVisible();

      for (const [status, count] of [
        ['critical', '10'],
        ['pending', '4']
      ]) {
        await hover(component.locator(`[data-label="${status}"]`));
        await expect
          .poll(tooltipSearch(mocks.indicators))
          .toBe(
            `{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"business_activity.name":{"$rg":"^ba1$"}}]},{"$or":[{"status":{"0":"${status}"}}]}]}`
          );
        await expect(
          tooltip(page).getByText(
            `Status: ${status[0].toUpperCase()}${status.slice(1)}`
          )
        ).toBeVisible();
        await expect(tooltip(page).getByText(`${count} KPIS`)).toBeVisible();
        for (const name of ['Memory', 'Ping']) {
          await expect(
            tooltip(page).getByText(name, { exact: true }).first()
          ).toBeVisible();
        }
      }
    });
  });
});
