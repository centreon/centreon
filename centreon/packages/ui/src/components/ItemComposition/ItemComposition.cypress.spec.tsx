import { ItemComposition } from '.';

interface Props {
  addButtonHidden?: boolean;
  addbuttonDisabled?: boolean;
  deleteButtonHidden?: boolean;
  secondaryLabel?: string;
}

const initialize = ({
  addButtonHidden,
  addbuttonDisabled,
  secondaryLabel,
  deleteButtonHidden
}: Props): unknown => {
  const addItem = cy.stub();
  const deleteItem = cy.stub();

  cy.mount({
    Component: (
      <ItemComposition
        addButtonHidden={addButtonHidden}
        addbuttonDisabled={addbuttonDisabled}
        labelAdd="Add"
        secondaryLabel={secondaryLabel}
        onAddItem={addItem}
      >
        <ItemComposition.Item
          deleteButtonHidden={deleteButtonHidden}
          labelDelete="Delete"
          onDeleteItem={deleteItem}
        >
          <div>Item 1</div>
        </ItemComposition.Item>
        <ItemComposition.Item
          deleteButtonHidden={deleteButtonHidden}
          labelDelete="Delete"
          onDeleteItem={deleteItem}
        >
          <div>Item 2</div>
        </ItemComposition.Item>
      </ItemComposition>
    )
  });

  return {
    addItem,
    deleteItem
  };
};

describe('ItemComposition', () => {
  it('displays the component', () => {
    initialize({});

    cy.contains('Item 1').should('be.visible');
    cy.contains('Item 2').should('be.visible');
    cy.findAllByTestId('Delete').should('have.length', 2);
    cy.findByTestId('Add').should('be.enabled');

    cy.makeSnapshot();
  });

  it('displays the component with a secondary label', () => {
    initialize({ secondaryLabel: 'Secondary label' });

    cy.contains('Secondary label').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays add button as hidden when the prop is set to true', () => {
    initialize({ addbuttonDisabled: true });

    cy.findByTestId('Add').should('be.disabled');

    cy.makeSnapshot();
  });

  it('does not display the add button when the prop is set to true', () => {
    initialize({ addButtonHidden: true });

    cy.findByTestId('Add').should('not.exist');

    cy.makeSnapshot();
  });

  it('does not display the delete button when the prop is set to true', () => {
    initialize({ deleteButtonHidden: true });

    cy.findByTestId('Delete').should('not.exist');

    cy.makeSnapshot();
  });

  it('calls the add function when the button is clicked', () => {
    const { addItem } = initialize({});

    cy.findByTestId('Add')
      .click()
      .then(() => {
        expect(addItem).to.have.been.calledWith();
      });
  });

  it('calls the delete function when the button is clicked', () => {
    const { deleteItem } = initialize({});

    cy.findAllByTestId('Delete')
      .eq(1)
      .click()
      .then(() => {
        expect(deleteItem).to.have.been.calledWith();
      });
  });
});
