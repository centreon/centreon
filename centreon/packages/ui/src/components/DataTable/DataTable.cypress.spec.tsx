import Delete from '@mui/icons-material/Delete';
import { Box } from '@mui/material';

import { IconButton } from '../..';
import { ColumnType } from '../../Listing/models';

import { DataTable } from '.';

const data = Array(5)
  .fill(0)
  .map((_, idx) => ({
    description: `Description ${idx}`,
    id: idx,
    title: `Entity ${idx}`
  }));

const CardActions = (): JSX.Element => {
  return (
    <IconButton ariaLabel="Delete" title="Delete" onClick={cy.stub()}>
      <Delete fontSize="small" />
    </IconButton>
  );
};

const initializeDataTableGrid = ({ hasActions, hasCardAction }): void => {
  cy.viewport(1200, 590);
  cy.mount({
    Component: (
      <DataTable variant="grid">
        {data.map(({ title, description }) => (
          <DataTable.Item
            Actions={<CardActions />}
            description={description}
            hasActions={hasActions}
            hasCardAction={hasCardAction}
            key={title}
            title={title}
          />
        ))}
      </DataTable>
    )
  });
};

const initializeDataTableEmpty = (canCreate = false): void => {
  cy.viewport(1200, 590);
  cy.mount({
    Component: (
      <DataTable isEmpty variant="grid">
        <DataTable.EmptyState
          canCreate={canCreate}
          labels={{
            actions: {
              create: 'Create'
            },
            title: 'Welcome',
            description: 'Description'
          }}
          onCreate={cy.stub()}
        />
      </DataTable>
    )
  });
};

const initializeDataTableListing = (): void => {
  cy.viewport(1200, 590);
  cy.mount({
    Component: (
      <Box sx={{ height: '100vh' }}>
        <DataTable variant="listing">
          <DataTable.Listing
            columns={[
              {
                getFormattedString: (row) => row.title,
                id: 'title',
                label: 'Title',
                type: ColumnType.string
              },
              {
                getFormattedString: (row) => row.description,
                id: 'description',
                label: 'Description',
                type: ColumnType.string
              }
            ]}
            rows={data}
          />
        </DataTable>
      </Box>
    )
  });
};

describe('DataTable: Grid', () => {
  it('displays items with title and description only', () => {
    initializeDataTableGrid({
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
      hasActions: true,
      hasCardAction: false
    });

    cy.findAllByLabelText('Delete').should('have.length', 5);

    cy.makeSnapshot();
  });

  it('displays items with card action only', () => {
    initializeDataTableGrid({
      hasActions: false,
      hasCardAction: true
    });

    cy.findAllByLabelText('view').should('have.length', 5);

    cy.makeSnapshot();
  });

  it('displays items with card action and bottom actions', () => {
    initializeDataTableGrid({
      hasActions: true,
      hasCardAction: true
    });

    cy.findAllByLabelText('view').should('have.length', 5);
    cy.findAllByLabelText('Delete').should('have.length', 5);

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

describe('DataTable: Listing', () => {
  it('displays the listing', () => {
    initializeDataTableListing();

    data.forEach(({ title, description }) => {
      cy.contains(title).should('be.visible');
      cy.contains(description).should('be.visible');
    });

    cy.makeSnapshot();
  });
});
