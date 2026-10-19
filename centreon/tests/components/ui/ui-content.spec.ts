import { expect, test } from '../fixtures';

/**
 * packages/ui CopyCommand, ConfirmationModal and DataTable.
 */
test.describe('CopyCommand', () => {
  const copy = 'ui/components/CopyCommand/CopyCommand/Default';
  const json =
    '{\n  "number": 1,\n  "boolean": true,\n  "array": [\n    {\n      "string": "text"\n    }\n  ]\n}';

  for (const { language, text, visible } of [
    {
      language: 'bash',
      text: '# a simple command\necho "hello" | grep "hel"',
      visible: ['# a simple command', 'echo']
    },
    {
      language: 'yaml',
      text: 'key:\n  with:\n    input: "input"',
      visible: ['key', '"input"']
    },
    {
      language: 'php',
      text: `<?php\necho 'Hello ' . htmlspecialchars($_POST["name"]) . '!';\n?>`,
      visible: ['echo', '"name"']
    },
    { language: 'json', text: json, visible: ['"number"', 'true'] }
  ]) {
    test(`highlights ${language} code`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(copy, {
        commandToCopy: 'echo "hello" | grep "hel"',
        language,
        text
      });
      for (const part of [...visible, language]) {
        await expect(
          component.getByText(part).first()
        ).toBeVisible();
      }
      await expect(component.getByTestId('Copy command')).toBeVisible();
      await expectThemedScreenshot(component, `copy-command-${language}`);
    });
  }

  test('offers no copy without a command, and follows the theme of the user', async ({
    mount
  }) => {
    const component = await mount(copy, { language: 'json', text: json });
    await expect(component.getByTestId('Copy command')).toHaveCount(0);

    const dark = await mount(copy, {
      dark: true,
      language: 'json',
      text: json
    });
    await expect(dark.locator('.hljs-keyword').first()).toHaveCSS(
      'color',
      'rgb(255, 123, 114)'
    );
  });

  test('copies the command to the clipboard', async ({
    context,
    mount,
    page
  }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    const component = await mount(copy, {
      commandToCopy: 'echo "hello" | grep "hel"',
      language: 'bash',
      text: '# a simple command\necho "hello" | grep "hel"'
    });
    await component.getByTestId('Copy command').click();
    await expect
      .poll(() => page.evaluate(() => navigator.clipboard.readText()))
      .toBe('echo "hello" | grep "hel"');
  });
});

test.describe('ConfirmationModal', () => {
  const modal =
    'ui/components/Modal/ConfirmationModal/ConfirmationModal/Default';

  test('shows its labels, static or after the data', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(modal);
    await component
      .getByRole('button', { name: 'Click to open modal' })
      .click();
    await expect(page.getByText('Title', { exact: true })).toBeVisible();
    await expect(page.getByText('Description', { exact: true })).toBeVisible();
    await expect(page.getByLabel('close')).toBeVisible();
    await expectThemedScreenshot(page, 'confirmation-modal');

    const dynamic = await mount(modal, { dynamicLabels: true });
    await dynamic.getByRole('button', { name: 'Click to open modal' }).click();
    await expect(page.getByText('Hello John')).toBeVisible();
    await expect(page.getByText('Description John')).toBeVisible();
  });

  test('applies its options', async ({ mount, page }) => {
    for (const [props, check] of [
      [
        { disabled: true },
        () =>
          expect(page.getByRole('button', { name: 'Confirm' })).toBeDisabled()
      ],
      [
        { isDanger: true },
        () =>
          expect(page.getByRole('button', { name: 'Confirm' })).toHaveAttribute(
            'data-is-danger',
            'true'
          )
      ],
      [
        { hasCloseButton: false },
        () => expect(page.getByLabel('close')).toHaveCount(0)
      ]
    ] as const) {
      const component = await mount(modal, props);
      await component
        .getByRole('button', { name: 'Click to open modal' })
        .click();
      await check();
    }
  });

  for (const [button, call] of [
    ['close', ''],
    ['Cancel', 'cancel John'],
    ['Confirm', 'confirm John']
  ] as const) {
    test(`closes on ${button}`, async ({ mount, page }) => {
      const component = await mount(modal);
      await component
        .getByRole('button', { name: 'Click to open modal' })
        .click();
      await expect(page.getByText('Title', { exact: true })).toBeVisible();
      await (button === 'close'
        ? page.getByLabel('close')
        : page.getByRole('button', { name: button })
      ).click();
      await expect(page.getByText('Title', { exact: true })).toHaveCount(0);
      await expect(component.getByTestId('calls')).toHaveText(call);
    });
  }
});

test.describe('DataTable', () => {
  test.use({ viewport: { height: 590, width: 1200 } });
  const titles = Array.from({ length: 5 }, (_, id) => `Entity ${id}`);

  for (const [label, props, deletes, views] of [
    ['without actions', {}, 0, 0],
    ['with actions', { hasActions: true }, 5, 0],
    ['with a card action', { hasCardAction: true }, 0, 5],
    ['with both', { hasActions: true, hasCardAction: true }, 5, 5]
  ] as const) {
    test(`draws a grid of items ${label}`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(
        'ui/components/DataTable/DataTable/Grid',
        props
      );
      for (const [index, title] of titles.entries()) {
        await expect(component.getByText(title)).toBeVisible();
        await expect(component.getByText(`Description ${index}`)).toBeVisible();
      }
      await expect(component.getByLabel('Delete')).toHaveCount(deletes);
      await expect(component.getByLabel('view')).toHaveCount(views);
      await expectThemedScreenshot(
        component,
        `data-table-grid-${label.replaceAll(' ', '-')}`
      );
    });
  }

  test('welcomes on an empty table, with a create action on demand', async ({
    mount
  }) => {
    const empty = await mount('ui/components/DataTable/DataTable/Empty');
    await expect(empty.getByText('Welcome')).toBeVisible();
    await expect(empty.getByText('Create')).toHaveCount(0);
    const creatable = await mount('ui/components/DataTable/DataTable/Empty', {
      canCreate: true
    });
    await expect(creatable.getByText('Create')).toBeVisible();
  });

  test('lists the items in a listing', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('ui/components/DataTable/DataTable/Listing');
    for (const [index, title] of titles.entries()) {
      await expect(component.getByText(title)).toBeVisible();
      await expect(component.getByText(`Description ${index}`)).toBeVisible();
    }
    await expectThemedScreenshot(component, 'data-table-listing');
  });
});
