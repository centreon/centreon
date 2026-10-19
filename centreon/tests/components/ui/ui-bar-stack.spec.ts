import { expect, test } from '../fixtures';

/**
 * packages/ui BarStack: a bar per value, laid out after the variant and the
 * size, with optional values, legend, title and tooltip.
 */
const story = 'ui/Graph/BarStack/BarStack/Default';
const data = [
  { label: 'Ok', value: 148 },
  { label: 'Unknown', value: 13 },
  { label: 'Warning', value: 16 },
  { label: 'Down', value: 62 }
];
const total = data.reduce((sum, { value }) => sum + value, 0);
const verticalOk =
  'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z';

test('stacks a bar per value, with its tooltip', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(story, { tooltip: true });
  await expect(component.locator('.visx-bar-rounded').first()).toHaveAttribute(
    'd',
    verticalOk
  );
  for (const { label, value } of data) {
    await expect(component.getByTestId(label)).toBeVisible();
    await component.getByTestId(label).dispatchEvent('mouseover');
    await expect(page.getByTestId(`tooltip-${label}`)).toContainText(
      `${label} : ${value}`
    );
  }
  await expectThemedScreenshot(component, 'bar-stack');
});

for (const [label, props, path, vertical] of [
  ['vertically', { variant: 'vertical' }, verticalOk, 'true'],
  [
    'horizontally',
    { variant: 'horizontal' },
    'M8,0 h231.69874476987445 h8v8 v295 v8h-8 h-231.69874476987445 a8,8 0 0 1 -8,-8 v-295 a8,8 0 0 1 8,-8z',
    'false'
  ]
] as const) {
  test(`stacks the bars ${label}`, async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(story, props);
    await expect(
      component.locator('.visx-bar-rounded').first()
    ).toHaveAttribute('d', path);
    await expect(
      component.locator(`[data-is-vertical="${vertical}"]`).first()
    ).toBeVisible();
    await expectThemedScreenshot(component, `bar-stack-${label}`);
  });
}

for (const [label, props, path] of [
  [
    'its container',
    { displayLegend: false, height: '300px', width: '300px' },
    'M8,95.18828451882847 h193 h8v8 v138.81171548117152 a8,8 0 0 1 -8,8 h-193 a8,8 0 0 1 -8,-8 v-138.81171548117152 v-8h8z'
  ],
  [
    'a small container',
    { displayLegend: true, height: '89px', title: 'host', width: '120px' },
    'M8,20.941422594142264 h94 h8v8 v18.058577405857733 a8,8 0 0 1 -8,8 h-94 a8,8 0 0 1 -8,-8 v-18.058577405857733 v-8h8z'
  ]
] as const) {
  test(`fits ${label}`, async ({ mount }) => {
    const component = await mount(story, props);
    await expect(
      component.locator('.visx-bar-rounded').first()
    ).toHaveAttribute('d', path);
    await expect(component.getByTestId('Ok')).toBeVisible();
  });
}

for (const [unit, format] of [
  ['number', (value: number) => String(value)],
  ['percentage', (value: number) => `${((value * 100) / total).toFixed(1)}%`]
] as const) {
  test(`writes the values on the bars as ${unit}s`, async ({ mount }) => {
    const component = await mount(story, { displayValues: true, unit });
    for (const [index, { value }] of data.entries()) {
      await expect(
        component.getByTestId('value').nth(index).locator(':scope > *').first()
      ).toHaveText(format(value));
    }
  });
}

test('shows the values, legend and title on demand only', async ({ mount }) => {
  const bare = await mount(story, {
    displayLegend: false,
    displayValues: false
  });
  await expect(bare.getByTestId('Ok')).toBeVisible();
  await expect(bare.getByTestId('value')).toHaveCount(0);
  await expect(bare.getByTestId('Legend')).toHaveCount(0);

  const titled = await mount(story, { title: 'host' });
  await expect(titled.getByTestId('Title')).toBeVisible();
});
