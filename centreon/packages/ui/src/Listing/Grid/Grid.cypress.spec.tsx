import Grid from './Grid';
import GridEmptyState from './EmptyState/GridEmptyState';
import GridItem from './Item/GridItem';

const data = Array(5)
  .fill(0)
  .map((_, idx) => ({
    description: `Description ${idx}`,
    id: idx,
    title: `Entity ${idx}`
  }));

const initializeDataTableGrid = ({
  hasActions,
  hasCardAction,
  canDelete
}): void => {
  cy.viewport(1200, 590);
  cy.mount({
    Component: (
      <Grid variant="grid" >
        {data.map(({ title, description }) => (
          <GridItem
            actions={<div />}
            description={description}
            key={title}
            title={title}
          />
        ))}
      </Grid>
    )
  });
};

const initializeDataTableEmpty = (canCreate = false): void => {
  cy.viewport(1200, 590);
  cy.mount({
    Component: (
      <Grid isEmpty variant="grid">
        <GridEmptyState
          canCreate={canCreate}
          labels={{
            actions: {
              create: 'Create'
            },
            title: 'Welcome'
          }}
          onCreate={cy.stub()}
        />
      </Grid>
    )
  });
};

describe('DataTable: Grid', () => {
  it('displays items with title and description only', () => {
    initializeDataTableGrid({
      canDelete: false,
      hasActions: false,
      hasCardAction: false
    });

    data.forEach(({ title, description }) => {
      cy.contains(title).should('be.visible');
      cy.contains(description).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('displays items with actions', () => {
    initializeDataTableGrid({
      canDelete: false,
      hasActions: true,
      hasCardAction: false
    });

    cy.findAllByLabelText('edit access rights').should('have.length', 5);
    cy.findAllByLabelText('edit').should('have.length', 5);

    cy.makeSnapshot();
  });

  it('displays items with delete action', () => {
    initializeDataTableGrid({
      canDelete: true,
      hasActions: true,
      hasCardAction: false
    });

    cy.findAllByLabelText('delete').should('have.length', 5);

    cy.makeSnapshot();
  });

  it('displays items with card action only', () => {
    initializeDataTableGrid({
      canDelete: false,
      hasActions: false,
      hasCardAction: true
    });

    cy.findAllByLabelText('view').should('have.length', 5);

    cy.makeSnapshot();
  });

  it('displays items with card action and bottom actions', () => {
    initializeDataTableGrid({
      canDelete: true,
      hasActions: true,
      hasCardAction: true
    });

    cy.findAllByLabelText('view').should('have.length', 5);
    cy.findAllByLabelText('delete').should('have.length', 5);
    cy.findAllByLabelText('edit access rights').should('have.length', 5);
    cy.findAllByLabelText('edit').should('have.length', 5);

    cy.makeSnapshot();
  });
});

describe('DataTable: Empty', () => {
  it('displays the title', () => {
    initializeDataTableEmpty();

    cy.contains('Welcome').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the title and the action button', () => {
    initializeDataTableEmpty(true);

    cy.contains('Welcome').should('be.visible');
    cy.contains('Create').should('be.visible');

    cy.makeSnapshot();
  });
});
