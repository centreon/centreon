import Widget from '.';

describe('Text Widget', () => {
  beforeEach(() => {
    const setPanelOptions = cy.stub();
    cy.mount({
      Component: (
        <Widget
          panelOptions={{ input: 'hello' }}
          setPanelOptions={setPanelOptions}
        />
      )
    });
  });
  it('displays the widget', () => {
    cy.contains('hello').should('be.visible');
  });
});
