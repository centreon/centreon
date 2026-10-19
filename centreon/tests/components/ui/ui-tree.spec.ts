import { expect, test } from '../fixtures';

/**
 * packages/ui StandaloneTree: the nodes of a tree, collapsed and expanded
 * on click, and its link styles.
 */
const tree = 'ui/Graph/Tree/Tree/Default';
const simpleNodes = [
  'T',
  'A',
  'A1',
  'A2',
  'A3',
  'C',
  'C1',
  'D',
  'D1',
  'D2',
  'D3',
  'E',
  'E1',
  'Z',
  'B',
  'B1'
];

test.use({ viewport: { height: 590, width: 1280 } });

test('draws every node, and collapses and expands a node on click', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(tree);
  const node = (name: string) => component.getByText(name, { exact: true });
  for (const name of simpleNodes) {
    await expect(node(name)).toBeVisible();
  }
  await expectThemedScreenshot(component, 'tree');

  await node('C').click();
  await expect(node('E')).toHaveCount(0);
  await expect(node('E1')).toHaveCount(0);

  await node('A').click();
  for (const name of ['A1', 'A2', 'A3', 'C']) {
    await expect(node(name)).toHaveCount(0);
  }
  await node('A').click();
  for (const name of ['A1', 'A2', 'A3', 'C']) {
    await expect(node(name)).toBeVisible();
  }

  // A leaf has nothing to collapse.
  await node('Z').click();
  await expect(node('Z')).toBeVisible();
});

test('expands the nodes asked by default', async ({ mount }) => {
  const component = await mount(tree, { expandCritical: true });
  for (const name of ['T', 'A', 'A3', 'C', 'E', 'E1']) {
    await expect(component.getByText(name, { exact: true })).toBeVisible();
  }
  for (const name of ['B1', 'D1']) {
    await expect(component.getByText(name, { exact: true })).toHaveCount(0);
  }
});

for (const [label, props] of [
  ['styled after their target', { styledLinks: true }],
  ['as steps', { linkType: 'step' }],
  ['as lines', { linkType: 'line' }]
] as const) {
  test(`draws the links ${label}`, async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(tree, props);
    await expect(component.getByText('T', { exact: true })).toBeVisible();
    await expectThemedScreenshot(
      component,
      `tree-links-${label.split(' ').at(-1)}`
    );
  });
}

test('collapses the clickable nodes of a custom content only', async ({
  mount
}) => {
  const component = await mount(tree, { complex: true });
  await expect(component.getByText('BA 3')).toBeVisible();
  await component.getByText('BA 2').click();
  await expect(component.getByText('BA 3')).toBeVisible();
  await component.getByText('2', { exact: true }).first().click();
  await expect(component.getByText('BA 3')).toHaveCount(0);
});
