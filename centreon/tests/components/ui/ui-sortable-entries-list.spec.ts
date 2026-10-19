import type { Locator, Page } from '@playwright/test';

import { expect, moveSortable, test } from '../fixtures';

/**
 * packages/ui SortableEntriesList: rows of one or several inputs, added,
 * removed and reordered with the focus kept where users expect it, a list
 * capped to a number of visible rows, and rows following their container.
 */
const story = 'ui/components/SortableEntriesList/SortableEntriesList';
const options = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'linux-server-standard' }
];
const macroA = {
  isPassword: false,
  name: 'MACRO_A',
  origin: 'direct',
  value: 'a'
};
const macroB = {
  isPassword: true,
  name: 'MACRO_B',
  origin: 'fromTpl',
  value: 'b'
};
const emptyMacro = { isPassword: false, name: '', origin: 'direct', value: '' };

const lastValues = async (component: Locator): Promise<unknown> =>
  JSON.parse(
    (await component.getByTestId('last-values').textContent()) ?? 'null'
  );

const expectLastValues = (component: Locator, expected: unknown) =>
  expect.poll(() => lastValues(component)).toEqual(expected);

const focusedTestId = (page: Page) =>
  page.evaluate(() => document.activeElement?.getAttribute('data-testid'));

test.describe('single input rows', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: 'configuration/hosts/templates',
      response: {
        meta: { limit: 10, page: 1, total: options.length },
        result: options
      }
    });
  });

  test('lays out a row per value with its actions and a trailing drag handle', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(`${story}/Templates`);
    await expect(
      component.getByRole('list', { name: 'Templates' })
    ).toBeVisible();
    const rows = component.getByRole('listitem');
    await expect(rows).toHaveCount(2);
    await expect(component.getByTestId('Template').nth(0)).toHaveValue(
      options[0].name
    );
    await expect(component.getByTestId('Template').nth(1)).toHaveValue(
      options[1].name
    );
    await expect(component.getByTestId('edit-template')).toHaveCount(2);
    await expect(component.getByTestId('delete-row')).toHaveCount(2);
    await expect(rows.first().locator('button').last()).toHaveAttribute(
      'data-testid',
      'drag-handle'
    );
    await expectThemedScreenshot(component, 'sortable-entries-list');

    // The inputs take the remaining width, the buttons keep a 32px pitch.
    const buttons = component
      .locator('[data-row-fields]')
      .first()
      .locator('xpath=following-sibling::*[1]')
      .locator('button');
    await expect(buttons).toHaveCount(3);
    const lefts = await buttons.evaluateAll((elements) =>
      elements.map((element) => element.getBoundingClientRect().left)
    );
    for (const [index, left] of lefts.slice(1).entries()) {
      expect(left - lefts[index]).toBe(32);
    }
    expect(
      (await component.locator('[data-row-fields]').first().boundingBox())
        ?.width
    ).toBeGreaterThan(500);
  });

  test('reports the plain values of the selected options, and disables those of the other rows', async ({
    mount,
    page
  }) => {
    const component = await mount(`${story}/Templates`);
    await component.getByTestId('Template').nth(1).click();
    await expect(
      page.getByRole('option', { name: options[0].name })
    ).toHaveAttribute('aria-disabled', 'true');
    await expect(
      page.getByRole('option', { name: options[2].name })
    ).not.toHaveAttribute('aria-disabled', 'true');
    await page.getByRole('option', { name: options[2].name }).click();
    await expect(component.getByTestId('Template').nth(1)).toHaveValue(
      options[2].name
    );
    await expectLastValues(component, [options[0], options[2]]);
  });

  test('focuses the rows it adds and the rows taking the place of removed ones', async ({
    mount,
    page
  }) => {
    const component = await mount(`${story}/Templates`);
    await component.getByTestId('Add').click();
    await expect(component.getByRole('listitem')).toHaveCount(3);
    await expect(component.getByTestId('Template').nth(2)).toHaveValue('');
    await expect(component.getByTestId('Template').nth(2)).toBeFocused();
    await expectLastValues(component, [options[0], options[1], null]);

    const three = await mount(`${story}/Templates`, { templates: 3 });
    await three.getByTestId('delete-row').first().click();
    await expect(three.getByRole('listitem')).toHaveCount(2);
    await expectLastValues(three, [options[1], options[2]]);
    await expect(three.getByTestId('Template').first()).toBeFocused();

    const two = await mount(`${story}/Templates`);
    await two.getByTestId('delete-row').nth(1).click();
    await expect(two.getByTestId('Template').first()).toBeFocused();
    await two.getByTestId('delete-row').first().click();
    await expect(two.getByRole('listitem')).toHaveCount(0);
    await expect.poll(() => focusedTestId(page)).toBe('Add');
  });

  test('reorders the rows with the keyboard, and passes the new positions to the actions', async ({
    mount,
    page
  }) => {
    const component = await mount(`${story}/Templates`);
    await moveSortable(
      page,
      component.getByTestId('drag-handle').first(),
      'ArrowDown'
    );
    await expect(component.getByTestId('Template').nth(0)).toHaveValue(
      options[1].name
    );
    await expect(component.getByTestId('Template').nth(1)).toHaveValue(
      options[0].name
    );
    await expectLastValues(component, [options[1], options[0]]);

    await component.getByTestId('edit-template').nth(1).click();
    await expect(component.getByTestId('calls')).toHaveText(
      JSON.stringify([[options[0], 1]])
    );
  });

  test('hides the drag handle when not draggable', async ({ mount }) => {
    const component = await mount(`${story}/Templates`, { draggable: false });
    await expect(component.getByTestId('delete-row')).toHaveCount(2);
    await expect(component.getByTestId('drag-handle')).toHaveCount(0);
  });
});

test.describe('multiple input rows', () => {
  const names = (component: Locator) =>
    component.locator('input[data-testid="macro-name"]');
  const values = (component: Locator) =>
    component.locator('input[data-testid="macro-value"]');

  test('renders every input of every row, with their class and actions', async ({
    mount
  }) => {
    const component = await mount(`${story}/Macros`);
    await expect(names(component)).toHaveCount(2);
    await expect(values(component)).toHaveCount(2);
    await expect(values(component).nth(1)).toHaveAttribute('type', 'password');
    await expect(component.getByRole('listitem').nth(0)).toHaveClass(
      /origin-direct/
    );
    await expect(component.getByRole('listitem').nth(1)).toHaveClass(
      /origin-fromTpl/
    );

    await component.getByTestId('toggle-password').first().click();
    await expect(values(component).first()).toHaveAttribute('type', 'password');

    await component.getByTestId('reset').click();
    await expect(component.getByRole('listitem')).toHaveCount(1);
    await expect(names(component)).toHaveValue('');
  });

  for (const copyOnChange of [false, true]) {
    test(`keeps the focus while typing${copyOnChange ? ' when the parent stores a copy' : ''}`, async ({
      mount,
      page
    }) => {
      const component = await mount(`${story}/Macros`, { copyOnChange });
      await names(component).first().fill('');
      await names(component).first().pressSequentially('HOST_IP');
      await expect(names(component).first()).toBeFocused();
      await values(component).first().fill('');
      await values(component).first().pressSequentially('10.0.0.1');
      await expect(values(component).first()).toBeFocused();
      await expectLastValues(component, [
        { ...macroA, name: 'HOST_IP', value: '10.0.0.1' },
        macroB
      ]);

      await component.getByTestId('Add').click();
      await expect.poll(() => focusedTestId(page)).toBe('macro-name');
      await page.keyboard.type('NEW');
      await values(component).nth(2).pressSequentially('value');
      await expectLastValues(component, [
        { ...macroA, name: 'HOST_IP', value: '10.0.0.1' },
        macroB,
        { ...emptyMacro, name: 'NEW', value: 'value' }
      ]);
    });
  }
});

test.describe('capped list', () => {
  test.use({ viewport: { height: 900, width: 1600 } });
  const scrollArea = (component: Locator) =>
    component.getByTestId('sortable-entries-scroll');
  const names = async (component: Locator) =>
    ((await lastValues(component)) as Array<{ name: string }>).map(
      ({ name }) => name
    );

  test('shows its visible rows and half the next one, fading it while rows are hidden', async ({
    mount
  }) => {
    const component = await mount(`${story}/Panel`, {
      count: 15,
      maxVisibleRows: 10
    });
    const area = scrollArea(component);
    const layout = await area.evaluate((element) => {
      const rows = element.querySelectorAll('li');
      const bottom = element.getBoundingClientRect().bottom;

      return {
        cut: bottom - rows[10].getBoundingClientRect().top,
        half: rows[10].getBoundingClientRect().height / 2,
        scrolls: element.scrollHeight > element.clientHeight,
        tenthFits: rows[9].getBoundingClientRect().bottom <= bottom
      };
    });
    expect(layout.scrolls).toBe(true);
    expect(layout.tenthFits).toBe(true);
    expect(Math.abs(layout.cut - layout.half)).toBeLessThanOrEqual(1);
    await expect(component.getByTestId('Add')).toBeVisible();
    await expect(area.getByTestId('Add')).toHaveCount(0);

    await expect(area).not.toHaveCSS('mask-image', 'none');
    await area.evaluate((element) =>
      element.scrollTo({ top: element.scrollHeight })
    );
    await expect(area).toHaveCSS('mask-image', 'none');
  });

  test('does not cap a shorter list, and scrolls to the rows it adds', async ({
    mount,
    page
  }) => {
    const short = await mount(`${story}/Panel`, {
      count: 5,
      maxVisibleRows: 10
    });
    await expect(scrollArea(short)).toHaveCSS('max-height', 'none');

    for (const count of [15, 10]) {
      const component = await mount(`${story}/Panel`, {
        count,
        maxVisibleRows: 10
      });
      await component.getByTestId('Add').click();
      await expect(component.getByRole('listitem')).toHaveCount(count + 1);
      await expect(scrollArea(component)).not.toHaveCSS('max-height', 'none');
      await expect.poll(() => focusedTestId(page)).toBe('macro-name');
      const inside = await page.evaluate(() => {
        const input = document.activeElement as Element;
        const area = input.closest(
          '[data-testid="sortable-entries-scroll"]'
        ) as Element;
        const row = input.getBoundingClientRect();
        const box = area.getBoundingClientRect();
        // 1px tolerance for sub-pixel row heights.
        return row.top >= box.top - 1 && row.bottom <= box.bottom + 1;
      });
      expect(inside).toBe(true);
    }
  });

  test('scrolls while a row moves past the visible area, with the keyboard or the pointer', async ({
    mount,
    page
  }) => {
    const keyboard = await mount(`${story}/Panel`, {
      count: 15,
      maxVisibleRows: 10
    });
    await moveSortable(
      page,
      keyboard.getByTestId('drag-handle').first(),
      'ArrowDown',
      12
    );
    await expect
      .poll(async () => (await names(keyboard)).indexOf('MACRO_1'))
      .toBe(12);
    expect(
      await scrollArea(keyboard).evaluate((element) => element.scrollTop)
    ).toBeGreaterThan(0);

    const pointer = await mount(`${story}/Panel`, {
      count: 15,
      maxVisibleRows: 10
    });
    const handle = await pointer
      .getByTestId('drag-handle')
      .first()
      .boundingBox();
    const area = await scrollArea(pointer).boundingBox();
    if (!handle || !area) {
      throw new Error('missing layout');
    }
    await page.mouse.move(
      handle.x + handle.width / 2,
      handle.y + handle.height / 2
    );
    await page.mouse.down();
    await page.mouse.move(
      handle.x + handle.width / 2,
      area.y + area.height - 5,
      { steps: 5 }
    );
    await expect
      .poll(() => scrollArea(pointer).evaluate((element) => element.scrollTop))
      .toBeGreaterThan(100);
    await page.mouse.move(
      handle.x + handle.width / 2,
      area.y + area.height - 4
    );
    await page.mouse.up();
    await expect
      .poll(async () => (await names(pointer)).indexOf('MACRO_1'))
      .toBeGreaterThan(10);
  });
});

test.describe('rows following their container', () => {
  test.use({ viewport: { height: 900, width: 1600 } });

  const fieldRects = (component: Locator) =>
    component
      .getByRole('listitem')
      .first()
      .locator('.MuiFormControl-root')
      .evaluateAll((elements) =>
        elements.map(
          (element) => element.getBoundingClientRect().toJSON() as DOMRect
        )
      );

  test('keeps the fields and buttons on one line down to 720px, resized at runtime', async ({
    mount
  }) => {
    const component = await mount(`${story}/Panel`, { count: 3 });
    for (const width of [1400, 900, 720, 1400]) {
      await component.getByTestId(`width-${width}`).click();
      await expect(component.getByTestId('panel')).toHaveCSS(
        'width',
        `${width}px`
      );
      await expect
        .poll(async () => {
          const [name, value] = await fieldRects(component);

          return (
            value.top === name.top && name.width >= 275 && value.width >= 275
          );
        })
        .toBe(true);
      const buttons = component
        .locator('[data-row-fields]')
        .first()
        .locator('xpath=following-sibling::*[1]')
        .locator('button');
      await expect(buttons).toHaveCount(4);
      const rects = await buttons.evaluateAll((elements) =>
        elements.map(
          (element) => element.getBoundingClientRect().toJSON() as DOMRect
        )
      );
      const panelRight =
        (await component.getByTestId('panel').boundingBox())?.x ?? 0;
      for (const [index, rect] of rects.slice(1).entries()) {
        expect(rect.top).toBe(rects[index].top);
        expect(rect.left - rects[index].left).toBe(32);
      }
      expect(rects[3].right).toBeLessThanOrEqual(panelRight + width);
    }
  });

  test('stacks the fields under 600px, whatever the viewport', async ({
    mount
  }) => {
    const component = await mount(`${story}/Panel`, { count: 3 });
    await component.getByTestId('width-560').click();
    await expect
      .poll(async () => {
        const [name, value] = await fieldRects(component);

        return value.top > name.bottom;
      })
      .toBe(true);
    await component.getByTestId('width-900').click();
    await expect
      .poll(async () => {
        const [name, value] = await fieldRects(component);

        return value.top === name.top;
      })
      .toBe(true);
  });
});
