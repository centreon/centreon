import type { Locator } from '@playwright/test';

import { expect, test } from '../fixtures';

/**
 * packages/ui BarChart: single, stacked and mixed bars in both orientations,
 * centered on zero or bounded, with their tooltip modes and legend options.
 * Each orientation and data set was a Cypress scenario; they are data here.
 */
const story = 'ui/Graph/BarChart/BarChart/Default';

test.use({ viewport: { height: 800, width: 1280 } });

// The chart spans the last 24 hours: freeze them for the screenshots.
test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2024-06-19T12:00:00Z'));
});

const expectAxes = async (component: Locator): Promise<void> => {
  await expect(
    component.locator('g[class*="visx-rows"] > line').first()
  ).toHaveAttribute('x2', /\d/);
  await expect(component.getByText('0 ms').first()).toBeVisible();
  await expect(component.getByText(':40 AM').first()).toBeVisible();
};

for (const orientation of ['horizontal', 'vertical'] as const) {
  for (const [data, bar, screenshot] of [
    ['ping', 'stacked-bar-3-0-0.08084', true],
    ['stacked', 'stacked-bar-3-0-0.16196', true],
    ['mixedStacked', 'stacked-bar-3-0-0.08644', false]
  ] as const) {
    test(`draws the ${data} bars ${orientation}ly, centered on zero or not`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      for (const axis of [undefined, { isCenteredZero: true }]) {
        const component = await mount(story, { axis, data, orientation });
        await expectAxes(component);
        await expect(component.getByTestId(bar)).toBeVisible();
        if (screenshot && !axis) {
          await expectThemedScreenshot(
            component,
            `bar-chart-${data}-${orientation}`
          );
        }
      }
    });
  }

  test(`draws ${orientation} bars with a custom style`, async ({ mount }) => {
    const component = await mount(story, {
      barStyle: { opacity: 0.5, radius: 0.5 },
      data: 'mixedStacked',
      orientation
    });
    await expectAxes(component);
    await expect(
      component.getByTestId('stacked-bar-3-0-0.08644')
    ).toBeVisible();
  });
}

test('describes a single bar in its tooltip', async ({ mount, page }) => {
  const component = await mount(story);
  await component.getByTestId('stacked-bar-10-0-7650.368581547736').hover();
  await expect(page.getByText('06/19/2024').first()).toBeVisible();
  await expect(
    page.getByText('Centreon-Server: Round-Trip Maximum Time').first()
  ).toBeVisible();
  await expect(page.getByText('7.47 KiB').first()).toBeVisible();
});

test('describes every metric of a stacked bar in its tooltip', async ({
  mount,
  page
}) => {
  const component = await mount(story, {
    data: 'stacked',
    tooltip: { mode: 'all', sortOrder: 'ascending' }
  });
  await component.getByTestId('stacked-bar-1-0-0.05296').hover();
  for (const text of [
    'Centreon-Server: Round-Trip Maximum Time',
    'Centreon-Server: Round-Trip Average Time',
    'Centreon-Server: Round-Trip Minimum Time',
    '0.05 ms',
    '0.02 ms',
    '0.11 ms'
  ]) {
    await expect(page.getByText(text).first()).toBeVisible();
  }
});

for (const [mode, shown, hidden] of [
  [
    'defined',
    ['Round-Trip Average Time'],
    ['Round-Trip Maximum Time', 'Round-Trip Minimum Time']
  ],
  ['all', ['Round-Trip Maximum Time', 'Round-Trip Minimum Time'], []]
] as const) {
  test(`lists ${mode === 'all' ? 'every metric' : 'the metrics with a value'} in the ${mode} tooltip mode`, async ({
    mount,
    page
  }) => {
    const component = await mount(story, {
      data: 'stackedWithUndefinedValues',
      tooltip: { mode, sortOrder: 'name' }
    });
    await page.mouse.move(0, 0);
    await component.getByTestId('stacked-bar-1-0-0.05296').hover();
    await expect(page.getByText('06/19/2024').first()).toBeVisible();
    for (const metric of shown) {
      await expect(
        page.locator(`[data-metric="Centreon-Server: ${metric}"]`)
      ).toBeVisible();
    }
    for (const metric of hidden) {
      await expect(
        page.locator(`[data-metric="Centreon-Server: ${metric}"]`)
      ).toHaveCount(0);
    }
  });
}

test('shows no tooltip in the hidden mode', async ({ mount, page }) => {
  const component = await mount(story, {
    data: 'stacked',
    tooltip: { mode: 'hidden', sortOrder: 'descending' }
  });
  await component.getByTestId('stacked-bar-1-0-0.05296').hover();
  await expect(page.getByText('06/19/2024')).toHaveCount(0);
  await expect(
    component.getByTestId('stacked-bar-3-0-0.12340000000000001')
  ).toBeVisible();
});

test('spans several days, and follows its bounds', async ({ mount }) => {
  const week = await mount(story, { data: 'lastWeek' });
  await expect(week.getByText('05/31/2023').first()).toBeVisible();
  await expect(week.getByText('06/07/2023').first()).toBeVisible();

  const bounded = await mount(story, { data: 'lastWeek', max: 1, min: -0.05 });
  await expect(bounded.getByText('1 s').first()).toBeVisible();
  await expect(bounded.getByText('1%').first()).toBeVisible();
});

test('stacks the bars despite a missing point, and after their stack keys', async ({
  expectThemedScreenshot,
  mount
}) => {
  const missing = await mount(story, { data: 'missingPoint' });
  await expect(missing.getByTestId('stacked-bar-2-0-139')).toBeVisible();

  const stacked = await mount(story, { data: 'stackKeys' });
  await expect(stacked.getByTestId('stacked-bar-3-0-0.05336')).toBeVisible();
  await expect(stacked.getByTestId('stacked-bar-4-0-0.06684')).toBeVisible();
  await expectThemedScreenshot(stacked, 'bar-chart-stack-keys');
});

test('shows only the calculations asked in the legend', async ({ mount }) => {
  const component = await mount(story, {
    data: 'lastWeek',
    legend: {
      mode: 'grid',
      placement: 'bottom',
      showCalculations: { avg: false, max: false, min: true }
    }
  });
  await expect(component.getByText('Min').first()).toBeVisible();
  await expect(component.getByText('Max')).toHaveCount(0);
  await expect(component.getByText('Avg')).toHaveCount(0);
});
