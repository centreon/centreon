import type { Locator, Page } from '@playwright/test';

import { expect, test } from '../fixtures';

/**
 * packages/ui Chart: the tooltip and its modes, the legend, the axis, the
 * line styles, lines mixed with bars, units, additional lines and bounds.
 */
const story = 'ui/Graph/Chart/Chart/Default';

test.use({ viewport: { height: 800, width: 1280 } });

// The chart spans the 24 hours before now.
test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2023-06-19T12:00:00Z'));
});

const zone = (component: Locator) =>
  component.getByTestId('graph-interaction-zone');

const expectDrawn = async (component: Locator): Promise<void> => {
  await expect(zone(component)).toHaveAttribute('height', '392');
  expect(Number(await zone(component).getAttribute('width'))).toBeGreaterThan(
    1149
  );
};

/** Hover a point of the chart, relative to its interaction zone. */
const hover = async (
  page: Page,
  component: Locator,
  x: number,
  y: number
): Promise<void> => {
  const box = await zone(component).boundingBox();
  await page.mouse.move((box?.x ?? 0) + x, (box?.y ?? 0) + y);
};

test.describe('Tooltip', () => {
  test('dates the hovered point and highlights the nearest metric', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(story);
    await expectDrawn(component);
    await expect(
      component.getByText('oracle-buffer-hit-ratio graph on srv-oracle-users')
    ).toBeVisible();
    for (const metric of ['hitratio', 'querytime', 'connTime']) {
      await expect(
        component.getByText(metric, { exact: true }).first()
      ).toBeVisible();
    }
    await hover(page, component, 240, 70);
    await expect(page.getByText('06/18/2023').first()).toBeVisible();
    await expectThemedScreenshot(component, 'chart-tooltip');

    await hover(page, component, 230, 26);
    await expect(page.locator('[data-metric="connTime"]')).toHaveAttribute(
      'data-highlight',
      'true'
    );
    await expect(page.locator('[data-metric="querytime"]')).toHaveAttribute(
      'data-highlight',
      'false'
    );
    await expect(page.locator('[data-metric="hitratio"]')).toHaveAttribute(
      'data-highlight',
      'false'
    );
  });

  test('skips the null values and lists the defined ones', async ({
    mount,
    page
  }) => {
    const nulls = await mount(story, { data: 'lastDayWithNullValues' });
    await hover(page, nulls, 1205, 100);
    await expect(page.locator('[data-metric="querytime"]')).toHaveCount(0);

    const incomplete = await mount(story, {
      data: 'lastDayWithIncompleteValues'
    });
    await hover(page, incomplete, 1100, 100);
    await expect(page.locator('[data-metric="querytime"]')).toBeVisible();
    await expect(page.locator('[data-metric="hitratio"]')).toBeVisible();
  });

  for (const [label, props, querytime, connTime] of [
    [
      'hides in the hidden mode',
      { tooltip: { mode: 'hidden', sortOrder: 'name' } },
      0,
      0
    ],
    [
      'sorts ascending',
      { tooltip: { mode: 'all', sortOrder: 'ascending' } },
      1,
      1
    ],
    [
      'sorts descending',
      { tooltip: { mode: 'all', sortOrder: 'descending' } },
      1,
      1
    ],
    [
      'leaves out the zeros in the defined mode',
      {
        data: 'lastDayWithZeroConnTime',
        tooltip: { mode: 'defined', sortOrder: 'name' }
      },
      1,
      0
    ],
    [
      'keeps the zeros in the all mode',
      {
        data: 'lastDayWithZeroConnTime',
        tooltip: { mode: 'all', sortOrder: 'name' }
      },
      1,
      1
    ]
  ] as const) {
    test(`${label}`, async ({ mount, page }) => {
      const component = await mount(story, props);
      await expectDrawn(component);
      await hover(page, component, 452, 26);
      await expect(page.locator('[data-metric="querytime"]')).toHaveCount(
        querytime
      );
      await expect(page.locator('[data-metric="connTime"]')).toHaveCount(
        connTime
      );
    });
  }
});

test('shades the curves sharing a color', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(story, { data: 'curvesWithSameColor' });
  await expect(zone(component)).toHaveAttribute('width', '1220');
  const icons = component.locator('[data-icon="true"]');
  for (const [index, color] of [
    'rgb(41, 175, 238)',
    'rgb(83, 191, 241)',
    'rgb(8, 34, 47)',
    'rgb(16, 70, 95)',
    'rgb(24, 105, 142)',
    'rgb(32, 140, 190)'
  ].entries()) {
    await expect(icons.nth(index)).toHaveCSS('background-color', color);
  }
  for (const [metric, stroke] of [
    ['1', 'rgb(41, 175, 238)'],
    ['21', 'rgb(32, 140, 190)'],
    ['17', 'rgb(24, 105, 142)'],
    ['13', 'rgb(16, 70, 95)'],
    ['9', 'rgb(8, 34, 47)'],
    ['5', 'rgb(83, 191, 241)']
  ]) {
    await expect(
      component.locator(`[data-metric="${metric}"]`)
    ).toHaveAttribute('stroke', stroke);
  }
  await expectThemedScreenshot(component, 'chart-same-colors');
});

test('places the legend as a list, or on a side', async ({ mount }) => {
  const list = await mount(story, {
    legend: { mode: 'list', placement: 'bottom' }
  });
  for (const calculation of ['Min:', 'Max:', 'Avg:']) {
    await expect(list.getByText(calculation)).toHaveCount(0);
  }
  await expect(
    list.locator('[data-display-side="false"]').first()
  ).toBeAttached();
  await expect(list.locator('[data-as-list="true"]').first()).toBeAttached();

  for (const legend of [
    { mode: 'grid', placement: 'left' },
    { mode: 'list', placement: 'right' }
  ] as const) {
    const side = await mount(story, { legend });
    await expect(
      side.locator('[data-display-side="true"]').first()
    ).toBeAttached();
    await expect(side.getByText(':00 AM').first()).toBeVisible();
  }
});

test('shows the calculations asked in the legend', async ({ mount }) => {
  const all = await mount(story);
  for (const text of [
    'Min: 70.31',
    'Min: 0.03',
    'Max: 88.03',
    'Max: 0.98',
    'Max: 0.97',
    'Avg: 78.07',
    'Avg: 0.5',
    'Avg: 0.51'
  ]) {
    await expect(all.getByText(text).first()).toBeVisible();
  }

  const minOnly = await mount(story, {
    data: 'ping',
    legend: {
      mode: 'grid',
      placement: 'bottom',
      showCalculations: { avg: false, max: false, min: true }
    }
  });
  await expect(minOnly.getByText('Min').first()).toBeVisible();
  await expect(minOnly.getByText(/^Max$/)).toHaveCount(0);
  await expect(minOnly.getByText('Avg')).toHaveCount(0);
});

test.describe('Axis', () => {
  test('hides its borders and grid lines on demand', async ({ mount }) => {
    const borderless = await mount(story, { axis: { showBorder: false } });
    for (const index of [0, 1, 2]) {
      await expect(
        borderless.locator('line[class*="visx-axis-line"]').nth(index)
      ).toHaveAttribute('stroke-width', '0');
    }
    const rows = (component: Locator) =>
      component.locator('g[class="visx-group visx-rows"]');
    const columns = (component: Locator) =>
      component.locator('g[class="visx-group visx-columns"]');
    for (const [axis, hasRows, hasColumns] of [
      [{ showGridLines: false }, 0, 0],
      [{ gridLinesType: 'horizontal' }, 1, 0],
      [{ gridLinesType: 'vertical' }, 0, 1]
    ] as const) {
      const component = await mount(story, { axis });
      await expectDrawn(component);
      await expect(rows(component)).toHaveCount(hasRows);
      await expect(columns(component)).toHaveCount(hasColumns);
    }
  });

  test('rotates its tick labels, and centers on zero', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const rotated = await mount(story, {
      axis: { yAxisTickLabelRotation: -35 }
    });
    await expect(
      rotated.locator('text[transform^="rotate(-35, -2, "]').first()
    ).toBeVisible();

    const centered = await mount(story, { axis: { isCenteredZero: true } });
    await expect(
      centered.getByText('0.8').first()
    ).toBeVisible();
    await expect(
      centered.getByText('-0.8').first()
    ).toBeVisible();
    await expectThemedScreenshot(centered, 'chart-centered-zero');
  });
});

test.describe('Line style', () => {
  test('draws natural and step curves, with points', async ({ mount }) => {
    const natural = await mount(story, { lineStyle: { curve: 'natural' } });
    for (const metric of ['13536', '13534', '13535']) {
      await expect(natural.locator(`[data-metric="${metric}"]`)).toBeVisible();
    }
    const step = await mount(story, {
      data: 'ping',
      lineStyle: { curve: 'step' }
    });
    for (const metric of ['1', '2', '3']) {
      await expect(step.locator(`[data-metric="${metric}"]`)).toBeVisible();
    }
    const points = await mount(story, { lineStyle: { showPoints: true } });
    await expect(points.locator('circle')).toHaveCount(864);
  });

  test('fills the area under the curves, with a transparency', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const area = await mount(story, { lineStyle: { showArea: true } });
    await expect(
      area
        .locator('path[fill="rgba(102, 153, 204, 0.19999999999999996)"]')
        .first()
    ).toBeVisible();
    await expectThemedScreenshot(area, 'chart-area');
    const transparent = await mount(story, {
      lineStyle: { areaTransparency: 20, showArea: true }
    });
    await expect(
      transparent.locator('path[fill="rgba(102, 153, 204, 0.8)"]').first()
    ).toBeVisible();
  });

  test('draws the lines wide, dotted or dashed', async ({ mount }) => {
    const wide = await mount(story, { lineStyle: { lineWidth: 6 } });
    await expect(wide.locator('path[stroke-width="6"]')).toHaveCount(3);
    const dotted = await mount(story, {
      lineStyle: { dotOffset: 10, lineWidth: 4 }
    });
    await expect(
      dotted.locator('path[stroke-width="4"]').first()
    ).toHaveAttribute('stroke-dasharray', '4 10');
    const dashed = await mount(story, {
      lineStyle: { dashLength: 5, dashOffset: 8 }
    });
    await expect(
      dashed.locator('path[stroke-width="2"]').first()
    ).toHaveAttribute('stroke-dasharray', '5 8');
  });

  test('styles one line only', async ({ mount }) => {
    const component = await mount(story, {
      lineStyle: [
        {
          dashLength: 5,
          dashOffset: 4,
          lineWidth: 1,
          metricId: 13534,
          showArea: true,
          showPoints: true
        }
      ]
    });
    await expect(component.locator('path.visx-area-closed')).toHaveAttribute(
      'stroke-dasharray',
      '5 4'
    );
    await expect(component.locator('circle')).toHaveCount(288);
  });
});

test.describe('Lines and bars', () => {
  for (const [data, bars] of [
    ['linesBars', ['stacked-bar-10-0-7650.368581547736']],
    [
      'linesBarsStacked',
      ['stacked-bar-2-0-6835', 'stacked-bar-10-0-14920.328518673756']
    ],
    [
      'linesBarsMixed',
      ['stacked-bar-10-0-7650.368581547736', 'stacked-bar-2-0-10']
    ]
  ] as const) {
    test(`draws ${data}, centered on zero or not`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      for (const axis of [undefined, { isCenteredZero: true }]) {
        const component = await mount(story, { axis, data });
        await expectDrawn(component);
        for (const metric of ['1', '3']) {
          await expect(
            component.locator(`path[data-metric="${metric}"]`)
          ).toBeVisible();
        }
        for (const bar of bars) {
          await expect(component.getByTestId(bar)).toBeVisible();
        }
        if (!axis) {
          await expectThemedScreenshot(component, `chart-${data}`);
        }
      }
    });
  }

  test('styles one line and one bar', async ({ mount }) => {
    const component = await mount(story, {
      barStyle: [{ metricId: 10, opacity: 0.5, radius: 0.3 }],
      data: 'linesBarsStacked',
      lineStyle: [{ dotOffset: 4, lineWidth: 3, metricId: 1, showArea: false }]
    });
    await expect(component.getByTestId('stacked-bar-2-0-1243')).toBeVisible();
    await expect(
      component.getByTestId('stacked-bar-10-0-8520.53622137828')
    ).toBeVisible();
  });

  test('changes the unit of each scale', async ({ mount, page }) => {
    const component = await mount(story, {
      customUnits: true,
      data: 'linesBarsMixed'
    });
    const selectors = component.getByTestId('unit-selector');
    await selectors.nth(0).locator('xpath=..').click();
    await page.getByLabel('B', { exact: true }).click();
    await expect(selectors.nth(0)).toHaveValue('B');
    await expect(component.getByText('8.79 KiB').first()).toBeVisible();
    await selectors.nth(1).locator('xpath=..').click();
    await page.getByLabel('%', { exact: true }).click();
    await expect(selectors.nth(1)).toHaveValue('%');
  });

  test('draws additional lines, and follows its bounds', async ({ mount }) => {
    const lines = await mount(story, {
      additionalLines: [
        { color: 'pink', unit: '%', yValue: 3 },
        { color: 'red', text: 'some text', unit: 'ms', yValue: 0.15 }
      ],
      data: 'ping'
    });
    await expect(lines.getByText('some text')).toBeVisible();
    await expect(lines.getByTestId('pink-3')).toBeAttached();
    await expect(lines.getByTestId('red-0.15')).toBeAttached();

    for (const [props, percent] of [
      [{}, '0.1%'],
      [{ boundariesUnit: 'ms' }, '2%']
    ] as const) {
      const bounded = await mount(story, {
        ...props,
        data: 'ping',
        max: 0.1,
        min: 0.01
      });
      await expect(bounded.getByText('0.1 ms').first()).toBeVisible();
      await expect(
        bounded.getByText(percent, { exact: true }).first()
      ).toBeVisible();
    }
  });

  test('calls the secondary action of a legend entry on right click', async ({
    mount
  }) => {
    const component = await mount(story, {
      data: 'ping',
      legend: { mode: 'grid', placement: 'bottom' },
      secondaryClick: true
    });
    await component.getByText('Packet Loss').first().click({ button: 'right' });
    await expect(component.getByTestId('secondary-clicks')).toHaveText('1');
  });
});
