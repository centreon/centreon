import AddButton from './AddButton';

const defaultProps = {
  addButtonDisabled: false,
  onAddItem: () => {}
};

const initialize = (values): void => {
  cy.mount({
    Component: <AddButton {...defaultProps} {...values} />
  });
};

describe('AddButton', () => {
  it('renders the add button with correct text and icon', () => {
    initialize({});
    cy.get('[data-testid="Add a host"]').should('be.visible');
    cy.get('[data-testid="Add a host"]').should('contain.text', 'Add a host');
    cy.get('svg[data-testid="AddCircleIcon"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('calls onAddItem when button is clicked', () => {
    const onAddItem = cy.stub();
    initialize({ onAddItem });

    cy.get('[data-testid="Add a host"]').click();
    cy.then(() => {
      expect(onAddItem).to.have.been.calledOnce;
    });
  });

  it('disables the button when addButtonDisabled is true', () => {
    initialize({ addButtonDisabled: true });

    cy.get('[data-testid="Add a host"]').should('be.disabled');
  });

  it('enables the button when addButtonDisabled is false', () => {
    initialize({ addButtonDisabled: false });

    cy.get('[data-testid="Add a host"]').should('not.be.disabled');
  });

  it('does not call onAddItem when disabled button is clicked', () => {
    const onAddItem = cy.stub();
    initialize({ addButtonDisabled: true, onAddItem });

    cy.get('[data-testid="Add a host"]').click({ force: true });
    cy.then(() => {
      expect(onAddItem).not.to.have.been.called;
    });
  });

  it('has correct aria-label for accessibility', () => {
    initialize({});

    cy.get('[data-testid="Add a host"]').should(
      'have.attr',
      'aria-label',
      'Add a host'
    );
  });
});
