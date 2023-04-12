import RichTextEditor from './RichTextEditor';

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
      cy.findByLabelText('bold').should('be.visible');
      cy.findByLabelText('italic').should('be.visible');
      cy.findByLabelText('underline').should('be.visible');
      cy.findByLabelText('strikethrough').should('be.visible');
      cy.findByLabelText('link').should('be.visible');
      cy.findByLabelText('RichTextEditor')
        .should('be.visible')
        .and('have.value', '');
      cy.get('#RichTextEditor').contains('Type here...');
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

      cy.get('#bold').click();
      cy.get('#italic').click();
      cy.get('#underline').click();
      cy.get('#strikethrough').click();

      cy.findByTestId('RichTextEditor').then((element: HTMLElement) => {
        checkElementStyleOnRichTextEditor({ check: true, element });
      });

      cy.get('#bold').click();
      cy.get('#italic').click();
      cy.get('#underline').click();
      cy.get('#strikethrough').click();

      cy.findByTestId('RichTextEditor').then((element: HTMLElement) => {
        checkElementStyleOnRichTextEditor({ check: false, element });
      });
    });

    it('add link with an url when link button is clicked', () => {
      cy.get('[data-testid="RichTextEditor"]').type('cypress');
      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.get('#link').click();

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
            .and('match', /^noopener/);

          cy.wrap(element)
            .should('have.attr', 'target')
            .and('match', /_blank/);
        });

      cy.get('[data-testid="RichTextEditor"]').focus().type('{selectAll}');

      cy.get('#link').click({ force: true });

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
  });

  describe('Uneditable Rich Text Editor', () => {
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
      cy.get('[data-testid="RichTextEditor"]')
        .invoke('attr', 'contenteditable')
        .should('eq', 'false');
      cy.findByLabelText('Undo').should('not.be.exist');
      cy.findByLabelText('Redo').should('not.be.exist');
      cy.findByLabelText('bold').should('not.be.exist');
      cy.findByLabelText('italic').should('not.be.exist');
      cy.findByLabelText('underline').should('not.be.exist');
      cy.findByLabelText('strikethrough').should('not.be.exist');
      cy.findByLabelText('link').should('not.be.exist');
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
      cy.get('#CypressComponentTest > p').should('be.exist');
      cy.get('[data-testid="CypressComponentTest"]').should('be.exist');
    });

    it('displays editor with custom placeholder', () => {
      cy.get('#CypressComponentTest > p').contains('Type Cypress test...');
    });

    it('displays editor with custom minimum input height', () => {
      cy.get('[data-testid="CypressComponentTest"]').should(
        'have.css',
        'min-height',
        '200px'
      );
    });
  });
});
