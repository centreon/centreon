import RichTextEditor from './RichTextEditor';
import { standardMacros } from './plugins/ToolbarPlugin/MacrosButton';
import { labelAlignPicker } from './translatedLabels';

interface CheckElementStyleOnRichTextEditorProps {
  check: boolean;
  element: HTMLElement;
}

const mockInititalStateToEditor =
  '{"root": {"children": [{"children": [{"detail": 0,"format": 0,"mode": "normal","style": "","text": "test Cypress","type": "text","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "paragraph","version": 1}],"direction": "ltr","format": "","indent": 0,"type": "root","version": 1}}';

const checkElementStyleOnRichTextEditor = ({
  element,
  check
}: CheckElementStyleOnRichTextEditorProps): void => {
  cy.wrap(element).should('be.visible');

  if (check) {
    cy.wrap(element)
      .contains('strong', 'cypress test')
      .should('have.css', 'font-style')
      .and('match', /italic/);

    cy.wrap(element)
      .contains('strong', 'cypress test')
      .should('have.css', 'text-decoration')
      .and('match', /underline line-through/);
  } else {
    cy.wrap(element)
      .contains('p', 'cypress test')
      .invoke('css', 'font-style')
      .and('not.match', /italic/);

    cy.wrap(element)
      .contains('p', 'cypress test')
      .invoke('css', 'text-decoration')
      .and('not.match', /underline line-through/);
  }
};

describe('Rich Text Editor', () => {
  describe('Editable Rich Text Editor', () => {
    beforeEach(() => {
      cy.mount({ Component: <RichTextEditor editable /> });
    });

    it('displays all elements when RichTextEditor is called with required props', () => {
      cy.findByLabelText('Undo').should('be.visible');
      cy.findByLabelText('Redo').should('be.visible');
      cy.findByLabelText('align picker').should('be.visible');
      cy.findByLabelText('block').should('be.visible');
      cy.findByLabelText('format').should('be.visible');
      cy.findByLabelText('link').should('be.visible');
      cy.findByLabelText('list').should('be.visible');

      cy.findByLabelText('RichTextEditor')
        .should('be.visible')
        .and('have.value', '');

      cy.findByLabelText('RichTextEditor').parent().contains('Type here...');

      cy.makeSnapshot();
    });

    it('displays all format actions when the format button is clicked', () => {
      cy.findByLabelText('format').click();

      cy.findByLabelText('bold').should('be.visible');
      cy.findByLabelText('italic').should('be.visible');
      cy.findByLabelText('underline').should('be.visible');
      cy.findByLabelText('strikethrough').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays changes with undo redo buttons', () => {
      cy.get('[data-testid="RichTextEditor"]').type('cypress test');
      cy.get('[data-testid="RichTextEditor"]').should(
        'have.text',
        'cypress test'
      );

      cy.findByLabelText('Undo').click();

      cy.get('[data-testid="RichTextEditor"]').should('have.text', '');

      cy.findByLabelText('Redo').click();

      cy.get('[data-testid="RichTextEditor"]').should(
        'have.text',
        'cypress test'
      );
    });

    it('displays changes with bold, italic, underline and strikethrough buttons', () => {
      cy.get('[data-testid="RichTextEditor"]').type('cypress test');

      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('italic').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('underline').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('strikethrough').click();

      cy.findByTestId('RichTextEditor').then((element: HTMLElement) => {
        checkElementStyleOnRichTextEditor({ check: true, element });
      });

      cy.findByLabelText('format').click();
      cy.findByLabelText('bold').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('italic').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('underline').click();

      cy.findByLabelText('format').click();
      cy.findByLabelText('strikethrough').click();

      cy.findByTestId('RichTextEditor').then((element: HTMLElement) => {
        checkElementStyleOnRichTextEditor({ check: false, element });
      });
    });

    it('adds link with an url when link button is clicked', () => {
      cy.get('[data-testid="RichTextEditor"]').type('cypress');
      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.findByLabelText('link').click();

      cy.findByLabelText('Saved link').should('have.text', 'https://');

      cy.findByLabelText('Edit link').click();
      cy.get('#InputLinkField').type('www.centreon.com').type('{enter}');

      cy.findByText('cypress')
        .parent()
        .then((element: HTMLElement) => {
          cy.wrap(element)
            .should('have.attr', 'href')
            .and('match', /^https:\/\/www.centreon.com/);

          cy.wrap(element)
            .should('have.attr', 'rel')
            .and('match', /^noreferrer/);

          cy.wrap(element)
            .should('have.attr', 'target')
            .and('match', /_blank/);
        });

      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.findByLabelText('link').click();

      cy.findByText('cypress')
        .parent()
        .then((element: HTMLElement) => {
          cy.wrap(element)
            .contains('p', 'cypress')
            .should('not.have.attr', 'href');

          cy.wrap(element).should('not.have.attr', 'rel');

          cy.wrap(element).should('not.have.attr', 'target');
        });
    });

    it('does not allow to open links in a new tab', () => {
      cy.mount({
        Component: <RichTextEditor editable openLinkInNewTab={false} />
      });

      cy.get('[data-testid="RichTextEditor"]').type('cypress');
      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.findByLabelText('link').click();

      cy.findByLabelText('Saved link').should('have.text', 'https://');

      cy.findByLabelText('Edit link').click();
      cy.get('#InputLinkField').type('www.centreon.com').type('{enter}');

      cy.findByText('cypress').parent().should('not.have.attr', 'target');

      cy.get('[data-testid="RichTextEditor"]').focus().clear();

      cy.get('[data-testid="RichTextEditor"]')
        .focus()
        .type('https://centreon.com');

      cy.findByText('https://centreon.com')
        .parent()
        .should('not.have.attr', 'target');
    });
  });

  describe('Rich Text Editor not editable', () => {
    beforeEach(() => {
      cy.mount({
        Component: (
          <RichTextEditor
            editable={false}
            initialEditorState={mockInititalStateToEditor}
          />
        )
      });
    });

    it('displays editor when editable props is false and an initialState exist', () => {
      cy.findByLabelText('Undo').should('not.exist');
      cy.findByLabelText('Redo').should('not.exist');
      cy.findByLabelText(labelAlignPicker).should('not.exist');
      cy.findByLabelText('block').should('not.exist');
      cy.findByLabelText('format').should('not.exist');
      cy.findByLabelText('link').should('not.exist');
      cy.findByLabelText('list').should('not.exist');
    });
  });

  describe('Rich Text Editor is disabled', () => {
    beforeEach(() => {
      cy.mount({
        Component: (
          <RichTextEditor
            disabled
            editable
            initialEditorState={mockInititalStateToEditor}
          />
        )
      });
    });

    it('displays editor when editable props is false and an initialState exist', () => {
      cy.findByLabelText('Undo').should('be.disabled');
      cy.findByLabelText('Redo').should('be.disabled');
      cy.findByLabelText(labelAlignPicker).should('be.disabled');
      cy.findByLabelText('block').should('be.disabled');
      cy.findByLabelText('format').should('be.disabled');
      cy.findByLabelText('link').should('be.disabled');
      cy.findByLabelText('list').should('be.disabled');
    });
  });

  describe('Custom style editable rich text editor ', () => {
    beforeEach(() => {
      cy.mount({
        Component: (
          <RichTextEditor
            editable
            minInputHeight={200}
            namespace="CypressComponentTest"
            placeholder="Type Cypress test..."
          />
        )
      });
    });
    it('displays editor with custom namespace', () => {
      cy.get('[data-testid="CypressComponentTest"] > p').should('be.exist');
      cy.get('[data-testid="CypressComponentTest"]').should('be.exist');
    });

    it('displays editor with custom placeholder', () => {
      cy.get('[data-testid="CypressComponentTest"]')
        .parent()
        .contains('Type Cypress test...');
    });

    it('displays editor with custom minimum input height', () => {
      cy.get('[data-testid="CypressComponentTest"]').should(
        'have.css',
        'min-height',
        '200px'
      );
    });
  });

  describe('Rich Text Editor with Macros Plugin', () => {
    beforeEach(() => {
      cy.mount({
        Component: <RichTextEditor displayMacrosButton editable />
      });
    });

    it('displays the Macros button when the "displayMacrosButton" prop is set to true', () => {
      cy.findByLabelText('Macros').click();

      standardMacros.forEach((macro) => {
        cy.findByText(macro).should('be.visible');
      });
    });

    it('ensures that the selected macro is inserted into the RichTextEditor', () => {
      cy.get('[data-testid="RichTextEditor"]').type('macro : ');

      standardMacros.forEach((macro) => {
        cy.findByLabelText('Macros').click();
        cy.findByText(macro).click();

        cy.get('[data-testid="RichTextEditor"]').should('contain', macro);
      });
    });
  });

  describe('Block type', () => {
    beforeEach(() => {
      cy.mount({
        Component: <RichTextEditor editable />
      });
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
      it(`displays ${blockType} when the corresponding block type button is selected`, () => {
        cy.get('[data-testid="RichTextEditor"]').type('Example');
        cy.findByLabelText('block').click();
        cy.findByText(blockType).click();

        cy.get('[data-testid="RichTextEditor"]').find(tag).should('be.visible');

        cy.makeSnapshot();
      });
    });
  });

  describe('List type', () => {
    beforeEach(() => {
      cy.mount({
        Component: <RichTextEditor editable />
      });
    });

    const testCases = [
      {
        listType: 'Unordered List',
        tag: 'ul'
      },
      {
        listType: 'Ordered List',
        tag: 'ol'
      }
    ];

    testCases.forEach(({ listType, tag }) => {
      it(`displays ${listType} when the corresponding list type button is selected`, () => {
        cy.get('[data-testid="RichTextEditor"]').type('Example');
        cy.findByLabelText('list').click();
        cy.findByLabelText(listType).click();

        cy.get('[data-testid="RichTextEditor"]').find(tag).should('be.visible');

        cy.makeSnapshot();
      });
    });
  });

  describe('Aligns', () => {
    beforeEach(() => {
      cy.mount({
        Component: <RichTextEditor editable />
      });
    });

    const testCases = ['Center', 'Right', 'Left'];

    testCases.forEach((label) => {
      it(`aligns the text to the ${label} when the corresponding button was clicked`, () => {
        cy.get('[data-testid="RichTextEditor"]').type('Example');

        cy.findByLabelText(labelAlignPicker).click();
        cy.findByLabelText(label).click();

        cy.findByText('Example')
          .parent()
          .should('have.css', 'text-align', label.toLowerCase());

        cy.makeSnapshot();
      });
    });
  });
});
