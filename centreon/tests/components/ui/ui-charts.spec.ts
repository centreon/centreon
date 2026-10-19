import heatMapData from '../../../packages/ui/src/Graph/HeatMap/HeatMapData.json';
import { expect, test } from '../fixtures';

/**
 * packages/ui PieChart, HeatMap and Timeline graphs.
 */
const pie = 'ui/Graph/PieChart/PieChart/Default';
const pieData = [
  { label: 'Ok', value: 148 },
  { label: 'Unknown', value: 13 },
  { label: 'Warning', value: 16 },
  { label: 'Down', value: 62 }
];
const total = pieData.reduce((sum, { value }) => sum + value, 0);

test.describe('Pie chart', () => {
  test('draws an arc per value, with its tooltip', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(pie, { tooltip: true });
    for (const { label, value } of pieData) {
      await expect(component.getByTestId(label)).toBeVisible();
      await component.getByTestId(label).dispatchEvent('mouseover');
      await expect(page.getByTestId(`tooltip-${label}`)).toContainText(
        `${label} : ${value}`
      );
    }
    await expectThemedScreenshot(component, 'pie-chart');
  });

  test('fits the size of its container', async ({ mount }) => {
    const component = await mount(pie, {
      displayLegend: false,
      height: '300px',
      width: '300px'
    });
    await expect(component.getByTestId('pieChart')).toHaveCSS('width', '300px');
    await expect(component.getByTestId('pieChart')).toHaveCSS(
      'height',
      '300px'
    );
  });

  test('shrinks a donut too large for its container', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(pie, {
      displayLegend: false,
      height: '120px',
      title: 'hosts',
      variant: 'donut',
      width: '120px'
    });
    await expect(component.locator('[data-variant="donut"]')).toHaveCSS(
      'width',
      '76px'
    );
    await expectThemedScreenshot(component, 'pie-chart-small-donut');
  });

  for (const variant of ['pie', 'donut'] as const) {
    test(`draws as a ${variant}`, async ({ mount }) => {
      const component = await mount(pie, { variant });
      await expect(
        component.locator(`[data-variant="${variant}"]`)
      ).toBeVisible();
    });
  }

  for (const [unit, format] of [
    ['number', (value: number) => String(value)],
    ['percentage', (value: number) => `${((value * 100) / total).toFixed(1)}%`]
  ] as const) {
    test(`writes the values on the arcs as ${unit}s`, async ({ mount }) => {
      const component = await mount(pie, { displayValues: true, unit });
      const values = component.getByTestId('value');
      for (const [index, { value }] of pieData.entries()) {
        await expect(
          values.nth(index).locator(':scope > *').first()
        ).toHaveText(format(value));
      }
    });
  }

  test('shows the values, legend and title on demand only', async ({
    mount
  }) => {
    const bare = await mount(pie, {
      displayLegend: false,
      displayValues: false
    });
    await expect(bare.getByTestId('value')).toHaveCount(0);
    await expect(bare.getByTestId('Legend')).toHaveCount(0);
    await expect(bare.getByTestId('Title')).toHaveCount(0);

    const full = await mount(pie, { displayLegend: true, title: 'host' });
    await expect(full.getByTestId('Legend')).toBeVisible();
    await expect(full.getByTestId('Title')).toBeVisible();
  });
});

test.describe('HeatMap', () => {
  const heatMap = 'ui/Graph/HeatMap/HeatMap/Default';
  const ids = heatMapData.map(({ id }) => id);

  test('draws a tile per entry, at any size', async ({
    expectThemedScreenshot,
    mount
  }) => {
    for (const props of [
      {},
      { height: '200px', tileSizeFixed: true, width: '590px' },
      { height: '200px', width: '590px' }
    ]) {
      const component = await mount(heatMap, props);
      for (const id of ids) {
        await expect(component.getByTestId(id)).toBeVisible();
      }
    }
    await expectThemedScreenshot(
      await mount(heatMap, { height: '200px', width: '590px' }),
      'heat-map-small'
    );
  });

  test('draws nothing in a container without width, and a single tile alone', async ({
    mount
  }) => {
    const empty = await mount(heatMap, { height: '200px', width: '0px' });
    await expect(empty.getByTestId(ids[0])).toHaveCount(0);
    const single = await mount(heatMap, { singleTile: true });
    await expect(single.getByTestId(ids[0])).toBeVisible();
    await expect(single.getByTestId(ids[1])).toHaveCount(0);
  });

  test('shows the tooltip of the tiles that meet its condition', async ({
    mount,
    page
  }) => {
    const tooltip = (counter: number) =>
      page.getByText(
        `This is the tooltip for Server-Service Counter-${counter}`
      );

    const none = await mount(heatMap);
    await none.getByTestId(ids[0]).hover();
    await expect(page.getByTestId(`tooltip-${ids[0]}`)).toHaveCount(0);

    const all = await mount(heatMap, { tooltip: 'all' });
    await all.getByTestId(ids[0]).hover();
    await expect(tooltip(53)).toBeVisible();

    const over100 = await mount(heatMap, { tooltip: 'over100' });
    await over100.getByTestId(ids[0]).hover();
    await expect(tooltip(53)).toHaveCount(0);
    await over100.getByTestId(ids[1]).hover();
    await expect(tooltip(779)).toBeVisible();
  });
});

test.describe('Timeline', () => {
  const timeline = 'ui/Graph/Timeline/Timeline/Default';
  const colors = ['green', 'red', 'gray', 'green', 'red', 'gray', 'green'];

  test('draws a bar per period in its color, on an hour axis', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(timeline);
    const bars = component.locator('path');
    await expect(bars).toHaveCount(colors.length);
    for (const [index, color] of colors.entries()) {
      await expect(bars.nth(index)).toHaveAttribute('fill', color);
    }
    await expect(
      component.locator('.visx-axis-bottom .visx-axis-tick').first()
    ).toContainText('11:00');
    await expectThemedScreenshot(component, 'timeline');
  });

  test('describes a period in the default tooltip', async ({ mount, page }) => {
    const component = await mount(timeline);
    await component.locator('path').first().dispatchEvent('mouseover');
    const tooltip = page.getByRole('tooltip');
    for (const text of [
      '09/09/2024 10:57 AM',
      '09/09/2024 11:15 AM',
      '17 minutes'
    ]) {
      await expect(tooltip.getByText(text)).toHaveCSS(
        'color',
        'rgb(0, 128, 0)'
      );
    }
  });

  test('describes a period in a custom tooltip', async ({ mount, page }) => {
    const component = await mount(timeline, { customTooltip: true });
    await component.locator('path').first().dispatchEvent('mouseover');
    const tooltip = page.getByTestId('tooltip-content');
    for (const text of [
      '09/09/2024 10:57 AM',
      '09/09/2024 11:15 AM',
      'green',
      '17 minutes'
    ]) {
      await expect(tooltip.getByText(text)).toBeVisible();
    }
  });
});
