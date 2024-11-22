import { FormControlLabel, Switch, Typography } from '@mui/material';
import { Provider, atom, createStore, useAtom, useSetAtom } from 'jotai';
import { identity, isNil, prop } from 'ramda';
import { ChangeEvent, useEffect } from 'react';
import { CrudPage } from '.';
import {
  Column,
  ColumnType,
  Method,
  SnackbarProvider,
  TestQueryProvider
} from '../..';
import { CrudPageRoot } from './CrudPageRoot';
import { CrudPageRootProps } from './models';

interface Item {
  id: number;
  name: string;
  description: string;
  subItems: Array<{ id: number; name: string }>;
}

interface Filters {
  hasDescription: boolean;
  isEven: boolean;
}

const generateItems = (count: number) =>
  Array(count)
    .fill(0)
    .map((_, idx) => ({
      id: idx,
      name: `Item ${idx}`,
      description: `Description ${idx}`,
      subItems: [{ id: 1, name: 'SubItem' }]
    }));

const filtersAtom = atom<Filters>({
  hasDescription: true,
  isEven: false
});

const Filters = () => {
  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (property: string) => (event: ChangeEvent<HTMLInputElement>) => {
      setFilters((current) => ({
        ...current,
        [property]: event.target.checked
      }));
    };

  return (
    <>
      <FormControlLabel
        control={
          <Switch
            checked={filters.hasDescription}
            onChange={change('hasDescription')}
          />
        }
        label="Has description"
      />
      <FormControlLabel
        control={
          <Switch checked={filters.isEven} onChange={change('isEven')} />
        }
        label="Is even"
      />
    </>
  );
};

const getSearchParameters = ({ filters }) => ({
  conditions: [
    {
      field: 'hasDescription',
      values: {
        $in: filters.hasDescription
      }
    },
    {
      field: 'isEven',
      values: {
        $in: filters.isEven
      }
    }
  ]
});

const labels = {
  title: 'Items',
  welcome: {
    title: 'Welcome to the items page',
    description: 'This page handles item'
  },
  actions: {
    create: 'Create item'
  },
  listing: {
    search: 'Search'
  }
};

const columns: Array<Column> = [
  {
    type: ColumnType.string,
    id: 'name',
    label: 'Name',
    getFormattedString: prop('name'),
    displaySubItemsCaret: true
  },
  {
    type: ColumnType.string,
    id: 'description',
    label: 'Description',
    getFormattedString: prop('description')
  }
];

const defaultProps: CrudPageRootProps<Item, Filters, Item, Item> = {
  baseEndpoint: '/listing',
  queryKeyName: 'listing',
  filtersAtom,
  getSearchParameters,
  labels,
  columns,
  filters: <Filters />,
  form: {
    getItem: {
      baseEndpoint: (id) => `/item/${id}`,
      adapter: identity,
      itemQueryKey: 'item'
    },
    Form: ({ initialValues }) => {
      const [askBeforeCloseForm, setAskBeforeCloseFormModal] = useAtom(
        CrudPage.askBeforeCloseFormModalAtom
      );
      const setOpenFormModal = useSetAtom(CrudPage.openFormModalAtom);

      useEffect(() => {
        if (!askBeforeCloseForm) {
          return;
        }

        setOpenFormModal(null);
        setAskBeforeCloseFormModal(false);
      }, [askBeforeCloseForm]);

      return (
        <Typography>
          This is a placeholder for the form
          <br />
          Initial values: {JSON.stringify(initialValues)}
        </Typography>
      );
    },
    labels: {
      add: {
        title: 'Add item',
        cancel: 'Cancel',
        confirm: 'Add'
      },
      update: {
        title: 'Update item',
        cancel: 'Cancel',
        confirm: 'Update'
      }
    }
  },
  deleteItem: {
    deleteEndpoint: (item) =>
      !isNil(item?.parent)
        ? `/listing/${item?.parent?.id}/subItems/${item?.id}`
        : `/listing/${item?.id}`,
    labels: {
      successMessage: (item) =>
        !isNil(item?.parent) ? 'Sub item deleted' : 'Item deleted',
      confirm: 'Delete',
      cancel: 'Cancel',
      title: (item) =>
        !isNil(item?.parent) ? 'Delete sub item' : 'Delete item',
      description: (item) =>
        !isNil(item?.parent) ? (
          <Typography>
            The sub item <strong>{item?.name}</strong> from the item{' '}
            <strong>{item?.parent?.name}</strong> will be deleted
          </Typography>
        ) : (
          <Typography>
            The item <strong>{item?.name}</strong> will be deleted
          </Typography>
        )
    }
  }
};

const listing = {
  result: generateItems(30),
  meta: {
    page: 1,
    total: 60,
    limit: 30
  }
};

const emptyListing = {
  result: [],
  meta: {
    page: 1,
    total: 0,
    limit: 30
  }
};

const initialize = (props: CrudPageRootProps<Item, Filters, Item, Item>) => {
  cy.interceptAPIRequest({
    alias: 'getEmptyListing',
    method: Method.GET,
    path: '**/empty-listing**',
    response: emptyListing
  });

  cy.interceptAPIRequest({
    alias: 'getListing',
    method: Method.GET,
    path: '**/listing**',
    response: listing
  });

  cy.interceptAPIRequest({
    alias: 'getListing',
    method: Method.GET,
    path: '**/listing**',
    response: listing
  });

  cy.interceptAPIRequest({
    alias: 'deleteItem',
    method: Method.DELETE,
    path: '**/listing/1',
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'deleteSubItem',
    method: Method.DELETE,
    path: '**/listing/0/subItems/1',
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'getItem',
    method: Method.GET,
    path: '**/item/0',
    response: generateItems(1)[0]
  });

  const store = createStore();

  cy.mount({
    Component: (
      <div style={{ height: '95vh' }}>
        <Provider store={store}>
          <SnackbarProvider>
            <TestQueryProvider>
              <CrudPageRoot {...props} />
            </TestQueryProvider>
          </SnackbarProvider>
        </Provider>
      </div>
    )
  });
};

describe('CrudPage', () => {
  it('displays a welcome message when no data are retrieved', () => {
    initialize({
      ...defaultProps,
      queryKeyName: 'empty-listing',
      baseEndpoint: '/empty-listing'
    });

    cy.waitForRequest('@getEmptyListing');

    cy.contains('Welcome to the items page').should('be.visible');
    cy.contains('This page handles item').should('be.visible');
    cy.contains('Items').should('be.visible');
    cy.get('button').contains('Create item').should('be.visible');

    cy.makeSnapshot();
  });

  it('opens the form modal when no data are retrieved and the corresponding button is clicked', () => {
    initialize({
      ...defaultProps,
      queryKeyName: 'empty-listing',
      baseEndpoint: '/empty-listing'
    });

    cy.waitForRequest('@getEmptyListing');

    cy.contains('Welcome to the items page').should('be.visible');
    cy.contains('This page handles item').should('be.visible');
    cy.contains('Items').should('be.visible');
    cy.get('button').contains('Create item').click();

    cy.contains('Add item').should('be.visible');
    cy.contains('This is a placeholder for the form').should('be.visible');
    cy.contains('Initial values:').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays items when items are retrieved', () => {
    initialize(defaultProps);

    cy.waitForRequest('@getListing').then(({ request }) => {
      const { searchParams } = request.url;

      expect(searchParams.get('page')).equal('1');
      expect(searchParams.get('limit')).equal('10');
      expect(searchParams.get('sort_by')).equal('{"name":"asc"}');
      expect(searchParams.get('search')).equal(
        '{"$and":[{"$or":[{"name":{"$rg":""}}]},{"$and":[{"$or":[{"hasDescription":{"$in":true}}]},{"$or":[{"isEven":{"$in":false}}]}]}]}'
      );
    });

    cy.contains('Items').should('be.visible');
    cy.get('button').contains('Create item').should('be.visible');
    cy.contains('Item 0').should('be.visible');
    cy.contains('Description 0').should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request when filters and search are updated', () => {
    initialize(defaultProps);

    cy.findAllByTestId('Search').eq(1).type('simple search');
    cy.findByLabelText('filters').click();
    cy.findByLabelText('Has description').click();
    cy.findByLabelText('Is even').click();

    cy.findAllByTestId('Search').eq(1).should('have.value', 'simple search');

    cy.findByLabelText('filters').click();

    cy.findByLabelText('Has description').should('not.exist');

    cy.wait(500);

    cy.waitForRequest('@getListing').then(({ request }) => {
      const { searchParams } = request.url;

      expect(searchParams.get('search')).equal(
        '{"$and":[{"$or":[{"name":{"$rg":"simple search"}}]},{"$and":[{"$or":[{"hasDescription":{"$in":false}}]},{"$or":[{"isEven":{"$in":true}}]}]}]}'
      );
    });

    cy.makeSnapshot();
  });

  it('displays the add modal when data are retrieved and the corresponding is clicked', () => {
    initialize(defaultProps);

    cy.waitForRequest('@getListing');

    cy.contains('Create item').click();

    cy.contains('Add item').should('be.visible');
    cy.contains('This is a placeholder for the form').should('be.visible');
    cy.contains('Initial values:').should('be.visible');

    cy.makeSnapshot();
  });

  describe('Delete with advanced labels', () => {
    it('deletes an item when items are retrieved and the corresponding button is clicked', () => {
      initialize(defaultProps);

      cy.findByTestId('delete-1').click();

      cy.contains('Delete item').should('be.visible');
      cy.contains('The item Item 1 will be deleted').should('be.visible');

      cy.get('button').contains('Delete').click();

      cy.waitForRequest('@deleteItem');

      cy.contains('Item deleted').should('be.visible');

      cy.makeSnapshot();
    });

    it('deletes a sub-item when items are retrieved and the corresponding button is clicked', () => {
      initialize({
        ...defaultProps,
        subItems: {
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'subItems',
          labelExpand: 'Expand',
          labelCollapse: 'Collapse'
        }
      });

      cy.findByTestId('Expand 0').click();
      cy.findByTestId('delete-0-1').click();

      cy.contains('Delete sub item').should('be.visible');
      cy.contains(
        'The sub item SubItem from the item Item 0 will be deleted'
      ).should('be.visible');

      cy.get('button').contains('Delete').click();

      cy.waitForRequest('@deleteSubItem');

      cy.contains('Sub item deleted').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Delete with basic labels', () => {
    it('deletes an item when items are retrieved and the corresponding button is clicked', () => {
      initialize({
        ...defaultProps,
        deleteItem: {
          ...defaultProps.deleteItem,
          labels: {
            title: 'Title',
            successMessage: 'This is a success',
            description: 'A small description',
            cancel: 'Cancel',
            confirm: 'Delete'
          }
        }
      });

      cy.findByTestId('delete-1').click();

      cy.contains('Title').should('be.visible');
      cy.contains('A small description').should('be.visible');

      cy.get('button').contains('Delete').click();

      cy.waitForRequest('@deleteItem');

      cy.contains('This is a success').should('be.visible');

      cy.makeSnapshot();
    });

    it('deletes a sub-item when items are retrieved and the corresponding button is clicked', () => {
      initialize({
        ...defaultProps,
        subItems: {
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'subItems',
          labelExpand: 'Expand',
          labelCollapse: 'Collapse'
        },
        deleteItem: {
          ...defaultProps.deleteItem,
          labels: {
            title: 'Title',
            successMessage: 'This is a success',
            description: 'A small description',
            cancel: 'Cancel',
            confirm: 'Delete'
          }
        }
      });

      cy.findByTestId('Expand 0').click();
      cy.findByTestId('delete-0-1').click();

      cy.contains('Title').should('be.visible');
      cy.contains('A small description').should('be.visible');

      cy.get('button').contains('Delete').click();

      cy.waitForRequest('@deleteSubItem');

      cy.contains('This is a success').should('be.visible');

      cy.makeSnapshot();
    });
  });

  it('cannot delete a sub-item when items are retrieved and the corresponding button is clicked', () => {
    initialize({
      ...defaultProps,
      subItems: {
        canDeleteSubItems: false,
        canCheckSubItems: false,
        enable: true,
        getRowProperty: () => 'subItems',
        labelExpand: 'Expand',
        labelCollapse: 'Collapse'
      },
      deleteItem: {
        ...defaultProps.deleteItem,
        labels: {
          title: 'Title',
          successMessage: 'This is a success',
          description: 'A small description',
          cancel: 'Cancel',
          confirm: 'Delete'
        }
      }
    });

    cy.findByTestId('Expand 0').click();
    cy.findByTestId('delete-0-1').should('not.exist');

    cy.makeSnapshot();
  });

  it('opens the update form when the corresponding button is clicked', () => {
    initialize(defaultProps);

    cy.findByTestId('edit-0').click();

    cy.waitForRequest('@getItem');

    cy.contains('Update item').should('be.visible');
    cy.contains('This is a placeholder for the form').should('be.visible');
    cy.contains(
      'Initial values: {"id":0,"name":"Item 0","description":"Description 0","subItems":[{"id":1,"name":"SubItem"}]}'
    ).should('be.visible');

    cy.makeSnapshot();
  });

  it('closes the update form when the update ubtton is clicked and the corresponding button is clicked', () => {
    initialize(defaultProps);

    cy.findByTestId('edit-0').click();

    cy.waitForRequest('@getItem');

    cy.contains('Update item').should('be.visible');
    cy.contains('This is a placeholder for the form').should('be.visible');
    cy.contains(
      'Initial values: {"id":0,"name":"Item 0","description":"Description 0","subItems":[{"id":1,"name":"SubItem"}]}'
    ).should('be.visible');

    cy.findByLabelText('close').click();

    cy.contains('Update item').should('not.exist');

    cy.makeSnapshot();
  });
});
