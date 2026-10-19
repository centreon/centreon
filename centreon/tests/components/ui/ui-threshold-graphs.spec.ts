import { expect, test } from '../fixtures';

/**
 * packages/ui Text, Gauge and SingleBar graphs: the value takes the color of
 * the threshold it reaches, and each graph shows the thresholds its way. The
 * three Cypress specs repeated the same cases; they are data here.
 */
const story = (graph: 'Gauge' | 'SingleBar' | 'Text') =>
  `ui/Graph/common/ThresholdGraphs/${graph}Graph` as const;

const colors = {
  critical: 'rgb(255, 74, 74)',
  success: 'rgb(136, 185, 34)',
  warning: 'rgb(253, 155, 39)'
};

for (const level of ['success', 'warning', 'critical'] as const) {
  test(`colors the value as ${level} after the thresholds`, async ({
    expectThemedScreenshot,
    mount
  }) => {
    for (const [graph, property] of [
      ['Text', 'color'],
      ['Gauge', 'fill'],
      ['SingleBar', 'fill']
    ] as const) {
      const component = await mount(story(graph), { thresholds: level });
      await expect(component.getByText('0.41 s')).toHaveCSS(
        property,
        colors[level]
      );
      await expectThemedScreenshot(
        component,
        `${graph.toLowerCase()}-${level}`
      );
    }
  });
}

test('lists the thresholds under the text', async ({ mount }) => {
  for (const [thresholds, warning, critical] of [
    ['success', 'Warning: 0.5 s', 'Critical: 1.5 s'],
    ['warning', 'Warning: 0.4 s', 'Critical: 1.5 s'],
    ['critical', 'Warning: 0.2 s', 'Critical: 0.3 s'],
    ['ranged', 'Warning: 0.13 s - 0.5 s', 'Critical: 0.55 s - 0.65 s']
  ] as const) {
    const component = await mount(story('Text'), { thresholds });
    await expect(component.getByText(warning)).toBeVisible();
    await expect(component.getByText(critical)).toBeVisible();
  }
});

test('draws the threshold arcs of the gauge, with their tooltips', async ({
  mount,
  page
}) => {
  for (const [thresholds, arcs] of [
    ['success', ['1.1500000000000001', '0.15000000000000013']],
    ['warning', ['1.25', '0.15000000000000013']],
    ['critical', ['0.6399999999999999', '0.54']],
    ['ranged', ['0.37', '0.09999999999999998']]
  ] as const) {
    const component = await mount(story('Gauge'), { thresholds });
    for (const arc of arcs) {
      await expect(component.getByTestId(`${arc}-arc`)).toBeVisible();
    }
  }
  const component = await mount(story('Gauge'), { thresholds: 'success' });
  // The arcs overlap: hover them through the event, like Cypress.
  await component.getByTestId('1.1500000000000001-arc').dispatchEvent('mouseover');
  await expect(page.getByText('Warning', { exact: true })).toBeVisible();
  await component.getByTestId('0.15000000000000013-arc').dispatchEvent('mouseover');
  await expect(page.getByText('Critical', { exact: true })).toBeVisible();
});

test('draws the threshold lines of the single bar, with their tooltips', async ({
  mount,
  page
}) => {
  for (const [thresholds, lines] of [
    ['success', ['warning-line-0.5', 'critical-line-1.5']],
    ['warning', ['warning-line-0.4', 'critical-line-1.5']],
    ['critical', ['warning-line-0.2', 'critical-line-0.3']],
    [
      'ranged',
      [
        'warning-line-0.13',
        'warning-line-0.5',
        'critical-line-0.55',
        'critical-line-0.65'
      ]
    ]
  ] as const) {
    const component = await mount(story('SingleBar'), { thresholds });
    for (const line of lines) {
      await expect(component.getByTestId(line)).toBeVisible();
      await expect(component.getByTestId(`${line}-tooltip`)).toBeVisible();
    }
  }
  const component = await mount(story('SingleBar'), { thresholds: 'success' });
  await component.getByTestId('warning-line-0.5-tooltip').hover();
  await expect(page.getByText('Warning', { exact: true })).toBeVisible();
  await component.getByTestId('critical-line-1.5-tooltip').hover();
  await expect(page.getByText('Critical', { exact: true })).toBeVisible();
});

test('shows the raw value on demand', async ({ mount }) => {
  for (const graph of ['Text', 'Gauge', 'SingleBar'] as const) {
    const component = await mount(story(graph), {
      displayAsRaw: true,
      thresholds: 'success'
    });
    await expect(component.getByText('0.40663333333 s')).toBeVisible();
  }
});

test('shows no value without data, and zero with empty data', async ({
  mount
}) => {
  for (const graph of ['Text', 'Gauge'] as const) {
    const component = await mount(story(graph), {
      data: 'none',
      thresholds: 'success'
    });
    await expect(component.getByText('0.41 s')).toHaveCount(0);
  }
  const empty = await mount(story('Text'), {
    data: 'empty',
    thresholds: 'success'
  });
  await expect(empty.getByText('0', { exact: true })).toBeVisible();
});

test.describe('Single bar options', () => {
  for (const [label, props] of [
    ['small', { size: 'small', thresholds: 'success' }],
    [
      'with its value on the left',
      { direction: 'row', textWidth: 100, thresholds: 'success' }
    ],
    [
      'small with its value on the left',
      { direction: 'row', size: 'small', textWidth: 70, thresholds: 'critical' }
    ]
  ] as const) {
    test(`draws the bar ${label}`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(story('SingleBar'), props);
      await expect(component.getByText('0.41 s')).toBeVisible();
      await expectThemedScreenshot(
        component,
        `single-bar-${label.replaceAll(' ', '-')}`
      );
    });
  }

  test('hides the value on demand', async ({ mount }) => {
    const component = await mount(story('SingleBar'), {
      showLabels: false,
      thresholds: 'success'
    });
    await expect(component.getByTestId('warning-line-0.5')).toBeVisible();
    await expect(component.getByText('0.41 s')).toHaveCount(0);
  });
});
