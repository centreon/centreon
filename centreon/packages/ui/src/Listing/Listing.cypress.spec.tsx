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

const generateSubItems = (parentIndex: number): Array<unknown> => {
  return tenElements.map((__, subIndex) => ({
    active: false,
    description: `Sub item ${subIndex + (parentIndex + 10) * 10} description`,
    disableCheckbox: false,
    disableRow: false,
    id: subIndex + (parentIndex + 10) * 10,
    name: `Sub Item ${subIndex + (parentIndex + 10) * 10}`,
    selected: false
  }));
};

const tenElements = new Array(10).fill(0);

const listingWithSubItems = tenElements.map((_, index) => ({
  active: false,
  description: `Entity ${index}`,
  disableCheckbox: false,
  disableRow: false,
  id: index,
  name: `E${index}`,
  selected: false,
  subItems: index % 2 === 0 ? generateSubItems(index) : undefined
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

const mountListingForSubItems = (): void => {
  cy.viewport('macbook-13');

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <Listing
          checkable
          columns={columnsWithSubItems}
          currentPage={1}
          limit={10}
          rows={listingWithSubItems}
          subItems={{
            canCheckSubItems: false,
            enable: true,
            getRowProperty: () => 'subItems',
            labelCollapse: 'Collapse',
            labelExpand: 'Expand'
          }}
          totalRows={10}
        />
      </div>
    )
  });
};

describe('Listing', () => {
  it('expands the row when the corresponding icon si clicked', () => {
    mountListingForSubItems();

    cy.contains('E0').should('be.visible');

    expandedItems.forEach((index) => {
      const subItems = generateSubItems(index);

      cy.findByLabelText(`Expand ${index}`).click();

      cy.findByLabelText(`Collapse ${index}`).should('exist');

      subItems.forEach(({ name, description }) => {
        cy.contains(name).should('exist');
        cy.contains(description).should('exist');
      });
    });

    cy.makeSnapshot();
  });

  it('collapses the row when the corresponding icon si clicked', () => {
    mountListingForSubItems();

    cy.contains('Sub item 100').should('be.visible');

    cy.findByLabelText('Collapse 0').click();

    cy.contains('Sub item 100').should('not.exist');

    cy.makeSnapshot();
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
