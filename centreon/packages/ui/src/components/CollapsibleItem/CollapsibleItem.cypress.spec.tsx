import { CollapsibleItem, Props } from './CollapsibleItem';

const title = 'Title';

const customizedTitle = <div>Customized title</div>;

const initialize = (props: Omit<Props, 'children'>): void => {
  cy.mount({
    Component: <CollapsibleItem {...props}>Content</CollapsibleItem>
  });
};

describe('CollapsibleItem', () => {
  it('displays the component collapsed by default', () => {
    initialize({ title });

    cy.contains(title).should('be.visible');
    cy.contains('Content').should('not.be.visible');
    cy.get('button[aria-expanded="false"]').should('exist');

    cy.makeSnapshot();
  });

  it('displays the component expanded when the corresponding prop is set to true', () => {
    initialize({ defaultExpanded: true, title });

    cy.contains(title).should('be.visible');
    cy.contains('Content').should('be.visible');
    cy.get('button[aria-expanded="true"]').should('exist');

    cy.makeSnapshot();
  });

  it('displays a customized title', () => {
    initialize({ title: customizedTitle });

    cy.contains('Customized title').should('be.visible');
    cy.get('button[aria-expanded="false"]').should('exist');

    cy.makeSnapshot();
  });

  it('displays the component as compact', () => {
    initialize({ compact: true, title });

    cy.contains(title).should('be.visible');
    cy.get('button[aria-expanded="false"]').should('exist');
    cy.get('div[data-compact="true"]').should('exist');

    cy.makeSnapshot();
  });

  it('displays the component as compact and expanded when the icon is clicked', () => {
    initialize({ compact: true, title });

    cy.contains(title).should('be.visible');
    cy.get('button[aria-expanded="false"]').should('exist');

    cy.get('button[aria-expanded="false"]').click();

    cy.get('button[aria-expanded="true"]').should('exist');
    cy.contains('Content').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the component as compact and a customized title', () => {
    initialize({ compact: true, title: customizedTitle });

    cy.contains('Customized title').should('be.visible');
    cy.get('button[aria-expanded="false"]').should('exist');
    cy.get('div[data-compact="true"]').should('exist');

    cy.makeSnapshot();
  });
});
