import { render } from '@test-setup/render';
import {
  cleanup,
  fireEvent,
  screen,
  waitFor,
  within
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { standardMacros } from './plugins/ToolbarPlugin/MacrosButton';
import RichTextEditor from './RichTextEditor';
import { labelAlignPicker } from './translatedLabels';

interface CheckElementStyleOnRichTextEditorProps {
  check: boolean;
  element: HTMLElement;
}

const mockInititalStateToEditor =
  '{"root": {"children": [{"children": [{"detail": 0,"format": 0,"mode": "normal","style": "","text": "test Cypress","type": "text","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "paragraph","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "root","version": 1}}';

const getEditor = (namespace = 'RichTextEditor'): HTMLElement =>
  screen.getByTestId(namespace);

// The first `tag` element, in document order, whose text contains `text`.
const getElementContaining = (
  container: HTMLElement,
  tag: string,
  text: string
): HTMLElement | undefined =>
  Array.from(container.querySelectorAll<HTMLElement>(tag)).find((element) =>
    element.textContent?.includes(text)
  );

const checkElementStyleOnRichTextEditor = async ({
  element,
  check
}: CheckElementStyleOnRichTextEditorProps): Promise<void> => {
  expect(element).toBeVisible();

  if (check) {
    await waitFor(() => {
      const strong = getElementContaining(element, 'strong', 'cypress test');

      expect(strong).toBeDefined();
      expect(getComputedStyle(strong as HTMLElement).fontStyle).toMatch(
        /italic/
      );
      expect(getComputedStyle(strong as HTMLElement).textDecoration).toMatch(
        /underline line-through/
      );
    });

    return;
  }

  await waitFor(() => {
    const paragraph = getElementContaining(element, 'p', 'cypress test');

    expect(paragraph).toBeDefined();
    expect(getComputedStyle(paragraph as HTMLElement).fontStyle).not.toMatch(
      /italic/
    );
    expect(
      getComputedStyle(paragraph as HTMLElement).textDecoration
    ).not.toMatch(/underline line-through/);
  });
};

// Selects the whole content, as `.focus().type('{selectAll}')`.
const selectAll = async (editor: HTMLElement): Promise<void> => {
  editor.focus();
  await userEvent.keyboard('{Control>}a{/Control}');
};

// A menu item click closes the menu: wait for it to be gone before the next
// one, so the queries do not pick an item of the closing menu.
const waitForMenusToClose = async (): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });
};

const clickMenuEntry = async (
  menuLabel: string,
  entryLabel: string
): Promise<void> => {
  await userEvent.click(screen.getByLabelText(menuLabel));
  await userEvent.click(await screen.findByLabelText(entryLabel));
  await waitForMenusToClose();
};

const insertLink = async (editor: HTMLElement): Promise<void> => {
  await userEvent.click(screen.getByLabelText('link'));

  const savedLink = await screen.findByLabelText('Saved link');

  expect(savedLink).toHaveTextContent(/^https:\/\/$/);

  // The link editor opens 30 px under the selected text, not where its
  // position starts (0, 0).
  const linkEditor = savedLink.closest('.MuiPaper-root') as HTMLElement;
  const text = screen.getByText('cypress').getBoundingClientRect();
  const root = editor.getBoundingClientRect();

  await waitFor(() => {
    const { m41: x, m42: y } = new DOMMatrix(
      getComputedStyle(linkEditor).transform
    );

    expect(Math.abs(x - (text.x - root.x))).toBeLessThan(1);
    expect(Math.abs(y - (text.bottom - root.y + 30))).toBeLessThan(1);
  });

  await userEvent.click(screen.getByLabelText('Edit link'));

  const input = await waitFor(() => {
    const field = document.querySelector<HTMLInputElement>('#InputLinkField');

    expect(field).not.toBeNull();

    return field as HTMLInputElement;
  });

  await userEvent.type(input, 'www.centreon.com');
  await userEvent.type(input, '{Enter}');

  expect(editor).toBeInTheDocument();
};

describe('Rich Text Editor', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Editable Rich Text Editor', () => {
    beforeEach(() => {
      render(<RichTextEditor editable />);
    });

    it('displays all elements when RichTextEditor is called with required props', async () => {
      expect(await screen.findByLabelText('Undo')).toBeVisible();
      expect(screen.getByLabelText('Redo')).toBeVisible();
      expect(screen.getByLabelText('align picker')).toBeVisible();
      expect(screen.getByLabelText('block')).toBeVisible();
      expect(screen.getByLabelText('format')).toBeVisible();
      expect(screen.getByLabelText('link')).toBeVisible();
      expect(screen.getByLabelText('list')).toBeVisible();

      const editor = screen.getByLabelText('RichTextEditor');

      expect(editor).toBeVisible();
      expect(editor).toHaveTextContent(/^$/);

      expect(
        within(editor.parentElement as HTMLElement).getByText('Type here...')
      ).toBeVisible();

      // Nothing to undo nor redo yet, and no menu open.
      expect(screen.getByLabelText('Undo')).toBeDisabled();
      expect(screen.getByLabelText('Redo')).toBeDisabled();
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('displays all format actions when the format button is clicked', async () => {
      await userEvent.click(screen.getByLabelText('format'));

      expect(await screen.findByLabelText('bold')).toBeVisible();
      expect(screen.getByLabelText('italic')).toBeVisible();
      expect(screen.getByLabelText('underline')).toBeVisible();
      expect(screen.getByLabelText('strikethrough')).toBeVisible();

      // The four actions in that order, in the menu of the highlighted format
      // button, none of them active.
      const items = within(screen.getByRole('menu')).getAllByRole('menuitem');

      expect(
        items.map((item) =>
          item.querySelector('svg')?.getAttribute('aria-label')
        )
      ).toEqual(['bold', 'italic', 'underline', 'strikethrough']);
      items.forEach((item) => {
        expect(item).toHaveAttribute('data-is-active', 'false');
      });
      expect(screen.getByLabelText('format')).toHaveAttribute(
        'data-is-active',
        'true'
      );
    });

    it('displays changes with undo redo buttons', async () => {
      const editor = getEditor();

      await userEvent.type(editor, 'cypress test');
      await waitFor(() => {
        expect(editor).toHaveTextContent(/^cypress test$/);
      });

      await userEvent.click(screen.getByLabelText('Undo'));

      await waitFor(() => {
        expect(editor).toHaveTextContent(/^$/);
      });

      await userEvent.click(screen.getByLabelText('Redo'));

      await waitFor(() => {
        expect(editor).toHaveTextContent(/^cypress test$/);
      });
    });

    it('displays changes with bold, italic, underline and strikethrough buttons', async () => {
      const editor = getEditor();

      await userEvent.type(editor, 'cypress test');

      await selectAll(editor);

      await clickMenuEntry('format', 'bold');
      await clickMenuEntry('format', 'italic');
      await clickMenuEntry('format', 'underline');
      await clickMenuEntry('format', 'strikethrough');

      await checkElementStyleOnRichTextEditor({
        check: true,
        element: getEditor()
      });

      await clickMenuEntry('format', 'bold');
      await clickMenuEntry('format', 'italic');
      await clickMenuEntry('format', 'underline');
      await clickMenuEntry('format', 'strikethrough');

      await checkElementStyleOnRichTextEditor({
        check: false,
        element: getEditor()
      });
    });

    it('adds link with an url when link button is clicked', async () => {
      const editor = getEditor();

      await userEvent.type(editor, 'cypress');
      await selectAll(editor);

      await insertLink(editor);

      await waitFor(() => {
        const link = screen.getByText('cypress').parentElement as HTMLElement;

        expect(link.getAttribute('href')).toMatch(
          /^https:\/\/www.centreon.com/
        );
        expect(link.getAttribute('rel')).toMatch(/^noreferrer/);
        expect(link.getAttribute('target')).toMatch(/_blank/);
      });

      await selectAll(editor);

      await userEvent.click(screen.getByLabelText('link'));

      await waitFor(() => {
        const element = screen.getByText('cypress')
          .parentElement as HTMLElement;

        expect(
          getElementContaining(
            element.parentElement as HTMLElement,
            'p',
            'cypress'
          )
        ).not.toHaveAttribute('href');
        expect(element).not.toHaveAttribute('rel');
        expect(element).not.toHaveAttribute('target');
        expect(screen.getByText('cypress').closest('a')).toBeNull();
      });
    });

    it('does not allow to open links in a new tab', async () => {
      cleanup();
      render(<RichTextEditor editable openLinkInNewTab={false} />);

      const editor = getEditor();

      await userEvent.type(editor, 'cypress');
      await selectAll(editor);

      await insertLink(editor);

      await waitFor(() => {
        const link = screen.getByText('cypress').parentElement as HTMLElement;

        expect(link).toHaveAttribute('href');
      });
      expect(screen.getByText('cypress').parentElement).not.toHaveAttribute(
        'target'
      );

      editor.focus();
      await userEvent.clear(editor);

      editor.focus();
      await userEvent.type(editor, 'https://centreon.com');

      const autoLink = (await screen.findByText('https://centreon.com'))
        .parentElement;

      expect(autoLink).toHaveAttribute('href');
      expect(autoLink).not.toHaveAttribute('target');
    });
  });

  describe('Rich Text Editor not editable', () => {
    beforeEach(() => {
      render(
        <RichTextEditor
          editable={false}
          initialEditorState={mockInititalStateToEditor}
        />
      );
    });

    it('displays editor when editable props is false and an initialState exist', () => {
      expect(screen.queryByLabelText('Undo')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Redo')).not.toBeInTheDocument();
      expect(screen.queryByLabelText(labelAlignPicker)).not.toBeInTheDocument();
      expect(screen.queryByLabelText('block')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('format')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('link')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('list')).not.toBeInTheDocument();
    });
  });

  describe('Rich Text Editor is disabled', () => {
    beforeEach(() => {
      render(
        <RichTextEditor
          disabled
          editable
          initialEditorState={mockInititalStateToEditor}
        />
      );
    });

    it('displays editor when editable props is false and an initialState exist', async () => {
      expect(await screen.findByLabelText('Undo')).toBeDisabled();
      expect(screen.getByLabelText('Redo')).toBeDisabled();
      expect(screen.getByLabelText(labelAlignPicker)).toBeDisabled();
      expect(screen.getByLabelText('block')).toBeDisabled();
      expect(screen.getByLabelText('format')).toBeDisabled();
      expect(screen.getByLabelText('link')).toBeDisabled();
      expect(screen.getByLabelText('list')).toBeDisabled();
    });
  });

  describe('Custom style editable rich text editor ', () => {
    beforeEach(() => {
      render(
        <RichTextEditor
          editable
          minInputHeight={200}
          namespace="CypressComponentTest"
          placeholder="Type Cypress test..."
        />
      );
    });

    it('displays editor with custom namespace', async () => {
      await waitFor(() => {
        expect(
          document.querySelector('[data-testid="CypressComponentTest"] > p')
        ).toBeInTheDocument();
      });
      expect(
        document.querySelector('[data-testid="CypressComponentTest"]')
      ).toBeInTheDocument();
    });

    it('displays editor with custom placeholder', async () => {
      expect(
        await within(
          getEditor('CypressComponentTest').parentElement as HTMLElement
        ).findByText('Type Cypress test...')
      ).toBeInTheDocument();
    });

    it('displays editor with custom minimum input height', () => {
      expect(
        getComputedStyle(getEditor('CypressComponentTest')).minHeight
      ).toBe('200px');
    });
  });

  describe('Rich Text Editor with Macros Plugin', () => {
    beforeEach(() => {
      render(<RichTextEditor displayMacrosButton editable />);
    });

    it('displays the Macros button when the "displayMacrosButton" prop is set to true', async () => {
      await userEvent.click(screen.getByLabelText('Macros'));

      for (const macro of standardMacros) {
        expect(await screen.findByText(macro)).toBeVisible();
      }
    });

    it('ensures that the selected macro is inserted into the RichTextEditor', async () => {
      const editor = getEditor();

      await userEvent.type(editor, 'macro : ');

      for (const macro of standardMacros) {
        await userEvent.click(screen.getByLabelText('Macros'));
        // The macro is inserted with `document.execCommand`, at the page
        // selection. `userEvent.click` would move that selection into the
        // menu item, as a pointer down does: `fireEvent.click` leaves it in
        // the editor.
        fireEvent.click(await screen.findByText(macro));

        await waitFor(() => {
          expect(editor).toHaveTextContent(macro);
        });
        await waitForMenusToClose();
      }
    });
  });

  describe('Block type', () => {
    beforeEach(() => {
      render(<RichTextEditor editable />);
    });

    const testCases = [
      {
        blockType: 'Huge',
        tag: 'h3'
      },
      {
        blockType: 'Large',
        tag: 'h5'
      },
      {
        blockType: 'Normal',
        tag: 'p'
      },
      {
        blockType: 'Small',
        tag: 'h6'
      }
    ];

    testCases.forEach(({ blockType, tag }) => {
      it(`displays ${blockType} when the corresponding block type button is selected`, async () => {
        const editor = getEditor();

        await userEvent.type(editor, 'Example');

        // The text starts as a paragraph: for Normal, it is made Huge first,
        // so choosing Normal has something to change.
        if (blockType === 'Normal') {
          await userEvent.click(screen.getByLabelText('block'));
          await userEvent.click(await screen.findByText('Huge'));
          await waitFor(() => {
            expect(editor.querySelector('h3')).toBeVisible();
          });
          await waitForMenusToClose();
        }

        await userEvent.click(screen.getByLabelText('block'));
        await userEvent.click(await screen.findByText(blockType));

        await waitFor(() => {
          expect(editor.querySelector(tag)).toBeVisible();
        });

        // The text is in that block alone, the menu is closed
        // and the change can be undone.
        expect(editor.querySelector(tag)).toHaveTextContent(/^Example$/);
        expect(editor.querySelectorAll('p, h3, h5, h6')).toHaveLength(1);
        await waitForMenusToClose();
        expect(screen.getByLabelText('Undo')).toBeEnabled();
      });
    });
  });

  describe('List type', () => {
    beforeEach(() => {
      render(<RichTextEditor editable />);
    });

    const testCases = [
      {
        icon: 'FormatListBulletedIcon',
        listType: 'Unordered List',
        tag: 'ul'
      },
      {
        icon: 'FormatListNumberedIcon',
        listType: 'Ordered List',
        tag: 'ol'
      }
    ];

    testCases.forEach(({ icon, listType, tag }) => {
      it(`displays ${listType} when the corresponding list type button is selected`, async () => {
        const editor = getEditor();

        await userEvent.type(editor, 'Example');
        await userEvent.click(screen.getByLabelText('list'));
        await userEvent.click(await screen.findByLabelText(listType));

        await waitFor(() => {
          expect(editor.querySelector(tag)).toBeVisible();
        });

        // The text is the list item, the menu is closed
        // and the list button shows the icon of the selected list type.
        expect(editor.querySelector(`${tag} > li`)).toHaveTextContent(
          /^Example$/
        );
        await waitForMenusToClose();
        await waitFor(() => {
          expect(
            within(screen.getByLabelText('list')).getByTestId(icon)
          ).toBeInTheDocument();
        });
      });
    });
  });

  describe('Aligns', () => {
    beforeEach(() => {
      render(<RichTextEditor editable />);
    });

    const testCases = ['Center', 'Right', 'Left'];

    testCases.forEach((label) => {
      it(`aligns the text to the ${label} when the corresponding button was clicked`, async () => {
        await userEvent.type(getEditor(), 'Example');

        await userEvent.click(screen.getByLabelText(labelAlignPicker));
        await userEvent.click(await screen.findByLabelText(label));

        await waitFor(() => {
          expect(
            getComputedStyle(
              screen.getByText('Example').parentElement as HTMLElement
            ).textAlign
          ).toBe(label.toLowerCase());
        });

        // The menu is closed and the align picker shows
        // the icon of the selected alignment.
        await waitForMenusToClose();
        await waitFor(() => {
          expect(
            within(screen.getByLabelText(labelAlignPicker)).getByTestId(
              `FormatAlign${label}Icon`
            )
          ).toBeInTheDocument();
        });
      });
    });
  });
});
