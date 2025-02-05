import Widget from '.';

describe('Text Widget', () => {
  beforeEach(() => {
    cy.mount({ Component: <Widget /> });
  });
  it('displays the widget', () => {
    cy.contains('Hello world').should('be.visible');
  });
});
