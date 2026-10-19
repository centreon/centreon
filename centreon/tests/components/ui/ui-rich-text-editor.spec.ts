import { expect, test } from '../fixtures';

/**
 * packages/ui RichTextEditor: the toolbar, the formats, links, blocks, lists,
 * alignments and macros, and the read-only and disabled states.
 */
const story = 'ui/RichTextEditor/RichTextEditor/Default';
const toolbar = [
  'Undo',
  'Redo',
  'align picker',
  'block',
  'format',
  'link',
  'list'
];
const initialState =
  '{"root": {"children": [{"children": [{"detail": 0,"format": 0,"mode": "normal","style": "","text": "test Cypress","type": "text","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "paragraph","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "root","version": 1}}';

test('offers its toolbar, formats and undo history', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(story, { editable: true });
  const editor = component.getByTestId('RichTextEditor');
  for (const label of toolbar) {
    await expect(component.getByLabel(label, { exact: true })).toBeVisible();
  }
  await expect(component.getByText('Type here...')).toBeVisible();
  await expectThemedScreenshot(component, 'rich-text-editor');

  await editor.pressSequentially('cypress test');
  await expect(editor).toHaveText('cypress test');
  await component.getByLabel('Undo').click();
  await expect(editor).toHaveText('');
  await component.getByLabel('Redo').click();
  await expect(editor).toHaveText('cypress test');

  const toggleFormats = async (): Promise<void> => {
    for (const format of ['bold', 'italic', 'underline', 'strikethrough']) {
      await component.getByLabel('format').click();
      await page.getByLabel(format, { exact: true }).click();
    }
  };
  await editor.focus();
  await page.keyboard.press('ControlOrMeta+A');
  await toggleFormats();
  const formatted = editor.locator('strong', { hasText: 'cypress test' });
  await expect(formatted).toHaveCSS('font-style', /italic/);
  await expect(formatted).toHaveCSS(
    'text-decoration',
    /underline line-through/
  );
  await toggleFormats();
  await expect(editor.locator('strong')).toHaveCount(0);
});

for (const openLinkInNewTab of [true, false]) {
  test(`links the selection${openLinkInNewTab ? ' in a new tab' : ''}`, async ({
    mount,
    page
  }) => {
    const component = await mount(story, { editable: true, openLinkInNewTab });
    const editor = component.getByTestId('RichTextEditor');
    await editor.pressSequentially('cypress');
    await page.keyboard.press('ControlOrMeta+A');
    await component.getByLabel('link').click();
    await expect(page.getByLabel('Saved link')).toHaveText('https://');
    await page.getByLabel('Edit link').click();
    // Typed after the https:// it offers.
    await page.locator('#InputLinkField').press('End');
    await page.locator('#InputLinkField').pressSequentially('www.centreon.com');
    await page.locator('#InputLinkField').press('Enter');
    const link = editor.locator('a', { hasText: 'cypress' });
    await expect(link).toHaveAttribute('href', /^https:\/\/www.centreon.com/);
    if (openLinkInNewTab) {
      await expect(link).toHaveAttribute('target', '_blank');
      await expect(link).toHaveAttribute('rel', /^noreferrer/);
      await editor.focus();
      await page.keyboard.press('ControlOrMeta+A');
      await component.getByLabel('link').click();
      await expect(editor.locator('a')).toHaveCount(0);
    } else {
      await expect(link).not.toHaveAttribute('target', /.*/);
    }
  });
}

test('hides its toolbar when read-only, and disables it when disabled', async ({
  mount
}) => {
  const readOnly = await mount(story, {
    editable: false,
    initialEditorState: initialState
  });
  await expect(readOnly.getByTestId('RichTextEditor')).toBeAttached();
  for (const label of toolbar) {
    await expect(readOnly.getByLabel(label, { exact: true })).toHaveCount(0);
  }

  const disabled = await mount(story, {
    disabled: true,
    editable: true,
    initialEditorState: initialState
  });
  for (const label of toolbar) {
    await expect(disabled.getByLabel(label, { exact: true })).toBeDisabled();
  }
});

test('takes a namespace, a placeholder and a minimum height', async ({
  mount
}) => {
  const component = await mount(story, {
    editable: true,
    minInputHeight: 200,
    namespace: 'CypressComponentTest',
    placeholder: 'Type Cypress test...'
  });
  const editor = component.getByTestId('CypressComponentTest');
  await expect(editor.locator('> p')).toBeAttached();
  await expect(component.getByText('Type Cypress test...')).toBeVisible();
  await expect(editor).toHaveCSS('min-height', '200px');
});

test('inserts the macros of its macros menu', async ({ mount, page }) => {
  const macros = [
    '{{SHORTDATETIME}}',
    '{{LONGDATETIME}}',
    '{{ALIAS}}',
    '{{ID}}',
    '{{NAME}}',
    '{{OUTPUT}}',
    '{{STATE}}'
  ];
  const component = await mount(story, {
    displayMacrosButton: true,
    editable: true
  });
  const editor = component.getByTestId('RichTextEditor');
  await editor.pressSequentially('macro : ');
  for (const macro of macros) {
    await component.getByLabel('Macros').click();
    await page.getByText(macro, { exact: true }).click();
    await expect(editor).toContainText(macro);
  }
});

for (const [picker, choice, tag, byLabel] of [
  ['block', 'Huge', 'h3', false],
  ['block', 'Large', 'h5', false],
  ['block', 'Small', 'h6', false],
  ['list', 'Unordered List', 'ul', true],
  ['list', 'Ordered List', 'ol', true]
] as const) {
  test(`turns the text into a ${choice.toLowerCase()} ${picker === 'block' ? 'block' : ''}`, async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(story, { editable: true });
    const editor = component.getByTestId('RichTextEditor');
    await editor.pressSequentially('Example');
    await component.getByLabel(picker, { exact: true }).click();
    await (byLabel
      ? page.getByLabel(choice, { exact: true })
      : page.getByText(choice, { exact: true })
    ).click();
    await expect(editor.locator(tag)).toBeVisible();
    await expectThemedScreenshot(component, `rich-text-editor-${tag}`);
  });
}

for (const alignment of ['Center', 'Right', 'Left']) {
  test(`aligns the text to the ${alignment.toLowerCase()}`, async ({
    mount,
    page
  }) => {
    const component = await mount(story, { editable: true });
    await component.getByTestId('RichTextEditor').pressSequentially('Example');
    await component.getByLabel('align picker').click();
    await page.getByLabel(alignment, { exact: true }).click();
    await expect(component.getByText('Example').locator('xpath=..')).toHaveCSS(
      'text-align',
      alignment.toLowerCase()
    );
  });
}
