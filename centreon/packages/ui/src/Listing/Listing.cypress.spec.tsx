import { useState } from 'react';

import { Button, Typography } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { ColumnType } from './models';

import Listing from '.';

interface Props {
  row: { name: string };
}

const ButtonColumn = ({ row }: Props): JSX.Element => (
  <Button size="small">Click to reveal details about {row.name}</Button>
);

const LargeText = (): JSX.Element => (
  <Typography sx={{ whiteSpace: 'normal' }}>
    This is a large text that fills the content
  </Typography>
);

const tenElements = new Array(10).fill(0);

const generateSubItems = (): Array<unknown> => {
  return tenElements.map((_, subIndex) => ({
    active: false,
    description: `Sub item ${subIndex * 10} description`,
    disableCheckbox: false,
    disableRow: false,
    id: subIndex * 10,
    name: `Sub Item ${subIndex * 10}`,
    selected: false
  }));
};

const listingWithSubItems = tenElements.map((_, index) => ({
  active: false,
  description: `Entity ${index}`,
  disableCheckbox: false,
  disableRow: false,
  id: index,
  name: `E${index}`,
  selected: false,
  subItems: index % 2 === 0 ? generateSubItems() : undefined
}));

const listingWithSubItems3Rows = Array(3)
  .fill(0)
  .map((_, index) => ({
    active: false,
    description: `Entity ${index}`,
    disableCheckbox: false,
    disableRow: false,
    id: index,
    name: `E${index}`,
    selected: false,
    subItems: index % 2 === 0 ? generateSubItems() : undefined
  }));

const defaultColumn = [
  {
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: ({ description }): string => description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  },
  {
    Component: LargeText,
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
    width: '100px'
  }
];

const columnsWithSubItems = [
  {
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: ({ description }): string => description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  },
  {
    Component: ButtonColumn,
    displaySubItemsCaret: true,
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
    width: '350px'
  }
];

const expandedItems = [0, 8];

const mountListingResponsive = (listingVariant: ListingVariant): void => {
  cy.viewport('macbook-13');

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <Listing
          isResponsive
          columns={defaultColumn}
          currentPage={1}
          limit={10}
          listingVariant={listingVariant}
          rows={listingWithSubItems}
          totalRows={10}
        />
      </div>
    )
  });
};

interface TestComponentProps {
  canCheckSubItems?: boolean;
  isSmallListing?: boolean;
}

const TestComponent = ({
  isSmallListing = false,
  canCheckSubItems = false
}: TestComponentProps): JSX.Element => {
  const [selectedRows, setSelectedRows] = useState([]);

  return (
    <Listing
      checkable
      columns={columnsWithSubItems}
      currentPage={1}
      limit={10}
      rows={isSmallListing ? listingWithSubItems3Rows : listingWithSubItems}
      selectedRows={selectedRows}
      subItems={{
        canCheckSubItems,
        enable: true,
        getRowProperty: () => 'subItems',
        labelCollapse: 'Collapse',
        labelExpand: 'Expand'
      }}
      totalRows={10}
      onSelectRows={setSelectedRows}
    />
  );
};

const mountListingForSubItems = ({
  isSmallListing = false,
  canCheckSubItems = false
}: TestComponentProps): void => {
  cy.viewport('macbook-13');

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <TestComponent
          canCheckSubItems={canCheckSubItems}
          isSmallListing={isSmallListing}
        />
      </div>
    )
  });
};

describe('Listing', () => {
  describe('Sub items', () => {
    it('expands the row when the corresponding icon is clicked', () => {
      mountListingForSubItems({});

      cy.contains('E0').should('be.visible');

      expandedItems.forEach((index) => {
        const subItems = generateSubItems(index);

        cy.findByLabelText(`Expand ${index}`).click();

        cy.findByLabelText(`Collapse ${index}`).should('exist');

        subItems.forEach(({ name, description }) => {
          cy.contains(name).should('exist');
          cy.contains(description).should('exist');
        });

        cy.findByLabelText(`Collapse ${index}`).click();
      });

      cy.makeSnapshot();
    });

    it('collapses the row when the corresponding icon is clicked', () => {
      mountListingForSubItems({});

      cy.findByLabelText('Expand 0').click();

      cy.contains('Sub item 10').should('be.visible');

      cy.findByLabelText('Collapse 0').click();

      cy.contains('Sub item 10').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays only one row as hovered when mutilple rows are expanded and a sub item row is hovered', () => {
      mountListingForSubItems({ isSmallListing: true });

      cy.findByLabelText('Expand 2').click();
      cy.findByLabelText('Expand 0').click();

      cy.findByLabelText('Collapse 0').should('be.visible');
      cy.findByLabelText('Collapse 2').should('be.visible');

      cy.contains('Sub Item 0').realHover();

      cy.get('[data-isHovered="true"]').should('have.length', 1);
      cy.get('[data-isHovered="true"]').contains('Sub Item 0').should('exist');

      cy.findByLabelText('Collapse 0').click();
      cy.findByLabelText('Collapse 2').click();
    });

    it('selects displayed rows when a row is selected and another row is selected with the shift key', () => {
      mountListingForSubItems({ canCheckSubItems: true, isSmallListing: true });

      cy.findByLabelText('Expand 0').click();

      cy.findByLabelText('Collapse 0').should('be.visible');

      cy.findAllByLabelText('Select row 0').eq(0).click();
      cy.get('body').type('{shift}', { release: false });
      cy.findByLabelText('Select row 50').eq(0).click();

      cy.findAllByLabelText('Select row 0').eq(0).should('be.checked');
      cy.findAllByLabelText('Select row 0').eq(1).should('be.checked');
      cy.findByLabelText('Select row 10').should('be.checked');
      cy.findByLabelText('Select row 20').should('be.checked');
      cy.findByLabelText('Select row 30').should('be.checked');
      cy.findByLabelText('Select row 40').should('be.checked');
      cy.findByLabelText('Select row 50').should('be.checked');

      cy.findByLabelText('Collapse 0').click();
    });

    it('selects displayed rows when the corresponding checkbox is clicked', () => {
      mountListingForSubItems({ canCheckSubItems: true, isSmallListing: true });

      cy.findByLabelText('Expand 0').click();

      cy.findByLabelText('Collapse 0').should('be.visible');

      cy.findAllByLabelText('Select all').eq(0).click();

      cy.findAllByLabelText('Select row 0').eq(0).should('be.checked');
      tenElements.forEach((_, index) => {
        if (index === 0) {
          cy.findAllByLabelText('Select row 0').eq(1).should('be.checked');

          return;
        }
        cy.findByLabelText(`Select row ${index * 10}`).should('be.checked');
      });
      cy.findByLabelText('Select row 1').should('be.checked');
      cy.findByLabelText('Select row 2').should('be.checked');

      cy.findByLabelText('Collapse 0').click();
    });
  });

  it('displays the last column on several lines in compact mode when the isResponsive prop is set', () => {
    mountListingResponsive(ListingVariant.compact);

    cy.get('.MuiTable-root').should(
      'have.css',
      'grid-template-rows',
      '30px 85px 85px 85px 85px 85px 85px 85px 85px 85px 85px'
    );

    cy.makeSnapshot();
  });

  it('displays the last column on several lines in extended mode when the isResponsive prop is set', () => {
    mountListingResponsive(ListingVariant.extended);

    cy.get('.MuiTable-root').should(
      'have.css',
      'grid-template-rows',
      '38px 85px 85px 85px 85px 85px 85px 85px 85px 85px 85px'
    );

    cy.makeSnapshot();
  });
});
