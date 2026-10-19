import { expect, test } from '../fixtures';

/**
 * packages/ui Zoom: the zoom buttons, the mouse wheel on the content and on
 * the minimap, the reset, and the scale of the minimap.
 */
const zoom = 'ui/components/Zoom/Zoom/Default';
const identity = 'matrix(1, 0, 0, 1, 0, 0)';

test('zooms in and out with the buttons, and resets', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(zoom, { showMinimap: true });
  const content = component.getByTestId('zoom-content');
  await expect(
    component.locator('g[clip-path="url(#zoom-clip-0)"]')
  ).toBeVisible();
  await expect(content).toHaveAttribute('transform', identity);
  await expectThemedScreenshot(component, 'zoom');

  await component.getByTestId('zoom in').click();
  await expect(content).toHaveAttribute('transform', /1\.2, 0, 0, 1\.2/);
  await component.getByTestId('clear').click();
  await expect(content).toHaveAttribute('transform', identity);
  await component.getByTestId('zoom out').click();
  await expect(content).toHaveAttribute('transform', /0\.8, 0, 0, 0\.8/);
});

for (const target of ['zoom-content', 'minimap-interaction']) {
  for (const [deltaY, scale] of [
    [20, '0.9'],
    [-20, '1.1']
  ] as const) {
    test(`zooms to ${scale} with the wheel on the ${target === 'zoom-content' ? 'content' : 'minimap'}`, async ({
      mount,
      page
    }) => {
      const component = await mount(zoom, { showMinimap: true });
      await component.getByTestId(target).hover();
      await page.mouse.wheel(0, deltaY);
      await expect(component.getByTestId('zoom-content')).toHaveAttribute(
        'transform',
        new RegExp(
          `${scale.replace('.', '\\.')}, 0, 0, ${scale.replace('.', '\\.')}`
        )
      );
    });
  }
}

test('hides the minimap on demand, and places it after its position', async ({
  expectThemedScreenshot,
  mount
}) => {
  const hidden = await mount(zoom, { showMinimap: false });
  await expect(hidden.getByTestId('zoom-content')).toHaveAttribute(
    'transform',
    identity
  );
  await expect(hidden.locator('g[clip-path="url(#zoom-clip-0)"]')).toHaveCount(
    0
  );

  const placed = await mount(zoom, {
    minimapPosition: 'bottom-right',
    showMinimap: true
  });
  await expect(
    placed.locator('g[clip-path="url(#zoom-clip-0)"]')
  ).toBeVisible();
  await expect(placed.locator('svg').first()).toHaveAttribute('height', '400');
  await expectThemedScreenshot(placed, 'zoom-minimap-bottom-right');
});

test('keeps the view when dragged without a button pressed', async ({
  mount,
  page
}) => {
  const component = await mount(zoom, { showMinimap: true });
  const box = await component.getByTestId('zoom-container').boundingBox();
  await page.mouse.move((box?.x ?? 0) + 400, (box?.y ?? 0) + 200);
  await page.mouse.move((box?.x ?? 0) + 600, (box?.y ?? 0) + 200);
  await expect(component.getByTestId('zoom-content')).toHaveAttribute(
    'transform',
    identity
  );
});

for (const [content, transform] of [
  ['several', 'transform: scale(0.684211) translate(-85px, -105px);'],
  ['negative', 'transform: scale(0.448276) translate(345px, 195px);']
] as const) {
  test(`scales the minimap down to the ${content === 'several' ? 'content' : 'content with negative translations'}`, async ({
    mount
  }) => {
    const component = await mount(zoom, { content, showMinimap: true });
    const minimap = component
      .getByTestId('minimap-interaction')
      .locator('xpath=..')
      .locator('g')
      .first();
    await expect(minimap).toHaveAttribute(
      'style',
      new RegExp(transform.replace(/[().]/g, '\\$&'))
    );
  });
}
