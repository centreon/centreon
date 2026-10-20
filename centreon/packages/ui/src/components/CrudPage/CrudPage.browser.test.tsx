import { FormControlLabel, Switch, Typography } from '@mui/material';

import {
  getRequestCalls,
  interceptAPIRequest,
  waitForRequest
} from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { atom, createStore, Provider, useAtom, useSetAtom } from 'jotai';
import { identity, isNil } from 'ramda';
import { type ChangeEvent, useEffect } from 'react';

import {
  type Column,
  ColumnType,
  Method,
  type SearchParameter,
  SnackbarProvider,
  TestQueryProvider
} from '../..';
import { CrudPage } from '.';
import { CrudPageRoot } from './CrudPageRoot';
import type { CrudPageRootProps } from './models';

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

const generateItems = (count: number): Array<Item> =>
  Array(count)
    .fill(0)
    .map((_, idx) => ({
      description: `Description ${idx}`,
      id: idx,
      name: `Item ${idx}`,
      subItems: [{ id: 1, name: 'SubItem' }]
    }));

const filtersAtom = atom<Filters>({
  hasDescription: true,
  isEven: false
});

const Filters = (): JSX.Element => {
  const [filters, setFilters] = useAtom(filtersAtom);

  const change =
    (property: string) =>
    (event: ChangeEvent<HTMLInputElement>): void => {
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

const getSearchParameters = ({
  filters
}: {
  filters: Filters;
}): SearchParameter =>
  ({
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
  }) as unknown as SearchParameter;

const labels = {
  actions: {
    create: 'Create item'
  },
  listing: {
    search: 'Search'
  },
  title: 'Items',
  welcome: {
    description: 'This page handles item',
    title: 'Welcome to the items page'
  }
};

const columns: Array<Column> = [
  {
    displaySubItemsCaret: true,
    getFormattedString: (row): string => (row as unknown as Item).name,
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: (row): string => (row as unknown as Item).description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  }
];

const defaultProps: CrudPageRootProps<Item, Filters, Item, Item> = {
  baseEndpoint: '/listing',
  columns,
  deleteItem: {
    deleteEndpoint: (item) =>
      !isNil(item?.parent)
        ? `/listing/${item?.parent?.id}/subItems/${item?.id}`
        : `/listing/${item?.id}`,
    labels: {
      cancel: 'Cancel',
      confirm: 'Delete',
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
        ),
      successMessage: (item) =>
        !isNil(item?.parent) ? 'Sub item deleted' : 'Item deleted',
      title: (item) =>
        !isNil(item?.parent) ? 'Delete sub item' : 'Delete item'
    }
  },
  filters: <Filters />,
  filtersAtom,
  form: {
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
      }, [askBeforeCloseForm, setAskBeforeCloseFormModal, setOpenFormModal]);

      return (
        <Typography>
          This is a placeholder for the form
          <br />
          Initial values: {JSON.stringify(initialValues)}
        </Typography>
      );
    },
    getItem: {
      adapter: identity,
      baseEndpoint: (id) => `/item/${id}`,
      itemQueryKey: 'item'
    },
    labels: {
      add: {
        cancel: 'Cancel',
        confirm: 'Add',
        title: 'Add item'
      },
      update: {
        cancel: 'Cancel',
        confirm: 'Update',
        title: 'Update item'
      }
    }
  },
  getSearchParameters,
  labels,
  queryKeyName: 'listing'
};

const listing = {
  meta: {
    limit: 30,
    page: 1,
    total: 60
  },
  result: generateItems(30)
};

const emptyListing = {
  meta: {
    limit: 30,
    page: 1,
    total: 0
  },
  result: []
};

const basicLabels = {
  cancel: 'Cancel',
  confirm: 'Delete',
  description: 'A small description',
  successMessage: 'This is a success',
  title: 'Title'
};

const subItems = {
  canCheckSubItems: false,
  enable: true,
  getRowProperty: (): string => 'subItems',
  labelCollapse: 'Collapse',
  labelExpand: 'Expand'
};

const initialize = (
  props: CrudPageRootProps<Item, Filters, Item, Item>
): void => {
  interceptAPIRequest({
    alias: 'getEmptyListing',
    method: Method.GET,
    path: '**/empty-listing**',
    response: emptyListing
  });

  interceptAPIRequest({
    alias: 'getListing',
    method: Method.GET,
    path: '**/listing**',
    response: listing
  });

  interceptAPIRequest({
    alias: 'deleteItem',
    method: Method.DELETE,
    path: '**/listing/1',
    response: {}
  });

  interceptAPIRequest({
    alias: 'deleteSubItem',
    method: Method.DELETE,
    path: '**/listing/0/subItems/1',
    response: {}
  });

  interceptAPIRequest({
    alias: 'getItem',
    method: Method.GET,
    path: '**/item/0',
    response: generateItems(1)[0]
  });

  const store = createStore();

  render(
    <div style={{ height: '95vh' }}>
      <Provider store={store}>
        <SnackbarProvider>
          <TestQueryProvider>
            <CrudPageRoot {...props} />
          </TestQueryProvider>
        </SnackbarProvider>
      </Provider>
    </div>
  );
};

// The deepest element whose whole text is the given one, even when it is
// split across elements (`<strong>`).
const byTextContent =
  (text: string) =>
  (_content: string, element: Element | null): boolean =>
    element?.textContent === text &&
    Array.from(element.children).every((child) => child.textContent !== text);

const formPlaceholder = /^This is a placeholder for the formInitial values:/;

// The modal closes once the deletion succeeded: the confirmation is gone and
// the listing is still there.
const expectDeleteModalClosed = async (title: string): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByText(title)).not.toBeInTheDocument();
  });
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  expect(screen.getByText('Item 1')).toBeVisible();
};

describe('CrudPage', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays a welcome message when no data are retrieved', async () => {
    initialize({
      ...defaultProps,
      baseEndpoint: '/empty-listing',
      queryKeyName: 'empty-listing'
    });

    await waitForRequest('@getEmptyListing');

    expect(await screen.findByText('Welcome to the items page')).toBeVisible();
    expect(screen.getByText('This page handles item')).toBeVisible();
    expect(screen.getByText('Items')).toBeVisible();
    expect(
      screen.getByText('Create item', { selector: 'button' })
    ).toBeVisible();

    // The welcome state replaces the listing.
    expect(screen.queryAllByTestId('Search')).toHaveLength(0);
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('opens the form modal when no data are retrieved and the corresponding button is clicked', async () => {
    initialize({
      ...defaultProps,
      baseEndpoint: '/empty-listing',
      queryKeyName: 'empty-listing'
    });

    await waitForRequest('@getEmptyListing');

    expect(await screen.findByText('Welcome to the items page')).toBeVisible();
    expect(screen.getByText('This page handles item')).toBeVisible();
    expect(screen.getByText('Items')).toBeVisible();
    await userEvent.click(
      screen.getByText('Create item', { selector: 'button' })
    );

    expect(await screen.findByText('Add item')).toBeVisible();
    expect(
      screen.getByText('This is a placeholder for the form', { exact: false })
    ).toBeVisible();
    expect(screen.getByText('Initial values:', { exact: false })).toBeVisible();

    // The add form starts without initial values.
    const dialog = screen.getByRole('dialog');

    expect(within(dialog).getByText('Add item')).toBeVisible();
    expect(
      within(dialog).getByText(formPlaceholder, { exact: false })
    ).toHaveTextContent(/^This is a placeholder for the formInitial values:$/);
  });

  it('displays items when items are retrieved', async () => {
    initialize(defaultProps);

    const { request } = await waitForRequest('@getListing');
    const { searchParams } = request.url;

    expect(searchParams.get('page')).toBe('1');
    expect(searchParams.get('limit')).toBe('10');
    expect(searchParams.get('sort_by')).toBe('{"name":"asc"}');
    expect(searchParams.get('search')).toBe(
      '{"$and":[{"$or":[{"name":{"$rg":""}}]},{"$and":[{"$or":[{"hasDescription":{"$in":true}}]},{"$or":[{"isEven":{"$in":false}}]}]}]}'
    );

    expect(await screen.findByText('Items')).toBeVisible();
    expect(
      await screen.findByText('Create item', { selector: 'button' })
    ).toBeVisible();
    expect(await screen.findByText('Item 0')).toBeVisible();
    expect(screen.getByText('Description 0')).toBeVisible();

    // The column headers, the pagination of the 60 items, and the edit and
    // delete actions of the first and last rows.
    expect(screen.getByText('Name')).toBeVisible();
    expect(screen.getByText('Description')).toBeVisible();
    expect(screen.getByText('1-10 of 60')).toBeVisible();
    expect(screen.getByTestId('edit-0')).toBeVisible();
    expect(screen.getByTestId('delete-0')).toBeVisible();
    expect(screen.getByTestId('edit-29')).toBeInTheDocument();
    expect(screen.getByTestId('delete-29')).toBeInTheDocument();
  });

  it('sends a request when filters and search are updated', async () => {
    initialize(defaultProps);

    await userEvent.type(
      (await screen.findAllByTestId('Search'))[1],
      'simple search'
    );
    await userEvent.click(screen.getByLabelText('filters'));
    await userEvent.click(await screen.findByLabelText('Has description'));
    await userEvent.click(screen.getByLabelText('Is even'));

    expect(screen.getAllByTestId('Search')[1]).toHaveValue('simple search');

    await userEvent.click(screen.getByLabelText('filters'));

    await waitFor(() => {
      expect(
        screen.queryByLabelText('Has description')
      ).not.toBeInTheDocument();
    });

    // The search is debounced (500 ms): rather than a fixed wait, wait for
    // the latest answered request to carry the new search.
    await waitFor(() => {
      expect(
        getRequestCalls('@getListing')
          .filter(({ answered }) => answered)
          .at(-1)
          ?.request.url.searchParams.get('search')
      ).toBe(
        '{"$and":[{"$or":[{"name":{"$rg":"simple search"}}]},{"$and":[{"$or":[{"hasDescription":{"$in":false}}]},{"$or":[{"isEven":{"$in":true}}]}]}]}'
      );
    });

    // The listing is still displayed.
    expect(await screen.findByText('Item 0')).toBeVisible();
  });

  it('displays the add modal when data are retrieved and the corresponding is clicked', async () => {
    initialize(defaultProps);

    await waitForRequest('@getListing');

    await userEvent.click(
      await screen.findByText('Create item', { selector: 'button' })
    );

    expect(await screen.findByText('Add item')).toBeVisible();
    expect(
      screen.getByText('This is a placeholder for the form', { exact: false })
    ).toBeVisible();
    expect(screen.getByText('Initial values:', { exact: false })).toBeVisible();

    // The add form starts without initial values.
    expect(
      within(screen.getByRole('dialog')).getByText(formPlaceholder)
    ).toHaveTextContent(/^This is a placeholder for the formInitial values:$/);
  });

  describe('Delete with advanced labels', () => {
    it('deletes an item when items are retrieved and the corresponding button is clicked', async () => {
      initialize(defaultProps);

      await userEvent.click(await screen.findByTestId('delete-1'));

      expect(await screen.findByText('Delete item')).toBeVisible();
      expect(
        screen.getByText(byTextContent('The item Item 1 will be deleted'))
      ).toBeVisible();

      await userEvent.click(
        within(screen.getByRole('dialog')).getByRole('button', {
          name: 'Delete'
        })
      );

      await waitForRequest('@deleteItem');

      expect(await screen.findByText('Item deleted')).toBeVisible();

      await expectDeleteModalClosed('Delete item');
    });

    it('deletes a sub-item when items are retrieved and the corresponding button is clicked', async () => {
      initialize({
        ...defaultProps,
        subItems
      });

      await userEvent.click(await screen.findByTestId('Expand 0'));
      await userEvent.click(await screen.findByTestId('delete-0-1'));

      expect(await screen.findByText('Delete sub item')).toBeVisible();
      expect(
        screen.getByText(
          byTextContent(
            'The sub item SubItem from the item Item 0 will be deleted'
          )
        )
      ).toBeVisible();

      await userEvent.click(
        within(screen.getByRole('dialog')).getByRole('button', {
          name: 'Delete'
        })
      );

      await waitForRequest('@deleteSubItem');

      expect(await screen.findByText('Sub item deleted')).toBeVisible();

      await expectDeleteModalClosed('Delete sub item');
    });
  });

  describe('Delete with basic labels', () => {
    it('deletes an item when items are retrieved and the corresponding button is clicked', async () => {
      initialize({
        ...defaultProps,
        deleteItem: {
          ...defaultProps.deleteItem,
          labels: basicLabels
        }
      });

      await userEvent.click(await screen.findByTestId('delete-1'));

      expect(await screen.findByText('Title')).toBeVisible();
      expect(screen.getByText('A small description')).toBeVisible();

      await userEvent.click(
        within(screen.getByRole('dialog')).getByRole('button', {
          name: 'Delete'
        })
      );

      await waitForRequest('@deleteItem');

      expect(await screen.findByText('This is a success')).toBeVisible();

      await expectDeleteModalClosed('Title');
    });

    it('deletes a sub-item when items are retrieved and the corresponding button is clicked', async () => {
      initialize({
        ...defaultProps,
        deleteItem: {
          ...defaultProps.deleteItem,
          labels: basicLabels
        },
        subItems
      });

      await userEvent.click(await screen.findByTestId('Expand 0'));
      await userEvent.click(await screen.findByTestId('delete-0-1'));

      expect(await screen.findByText('Title')).toBeVisible();
      expect(screen.getByText('A small description')).toBeVisible();

      await userEvent.click(
        within(screen.getByRole('dialog')).getByRole('button', {
          name: 'Delete'
        })
      );

      await waitForRequest('@deleteSubItem');

      expect(await screen.findByText('This is a success')).toBeVisible();

      await expectDeleteModalClosed('Title');
    });
  });

  it('cannot delete a sub-item when items are retrieved and the corresponding button is clicked', async () => {
    initialize({
      ...defaultProps,
      deleteItem: {
        ...defaultProps.deleteItem,
        labels: basicLabels
      },
      subItems: {
        ...subItems,
        canDeleteSubItems: false
      }
    });

    await userEvent.click(await screen.findByTestId('Expand 0'));

    // The sub-item is displayed, without any action,
    // and its parent keeps its own.
    expect(await screen.findByText('SubItem')).toBeVisible();
    expect(screen.queryByTestId('delete-0-1')).not.toBeInTheDocument();
    expect(screen.queryByTestId('edit-0-1')).not.toBeInTheDocument();
    expect(screen.getByTestId('edit-0')).toBeVisible();
    expect(screen.getByTestId('delete-0')).toBeVisible();
  });

  it('opens the update form when the corresponding button is clicked', async () => {
    initialize(defaultProps);

    await userEvent.click(await screen.findByTestId('edit-0'));

    await waitForRequest('@getItem');

    expect(await screen.findByText('Update item')).toBeVisible();
    expect(
      screen.getByText('This is a placeholder for the form', { exact: false })
    ).toBeVisible();
    expect(
      await screen.findByText(
        'Initial values: {"description":"Description 0","id":0,"name":"Item 0","subItems":[{"id":1,"name":"SubItem"}]}',
        { exact: false }
      )
    ).toBeVisible();

    // The update form is a dialog over the listing.
    expect(
      within(screen.getByRole('dialog')).getByText('Update item')
    ).toBeVisible();
  });

  it('closes the update form when the update ubtton is clicked and the corresponding button is clicked', async () => {
    initialize(defaultProps);

    await userEvent.click(await screen.findByTestId('edit-0'));

    await waitForRequest('@getItem');

    expect(await screen.findByText('Update item')).toBeVisible();
    expect(
      screen.getByText('This is a placeholder for the form', { exact: false })
    ).toBeVisible();
    expect(
      await screen.findByText(
        'Initial values: {"description":"Description 0","id":0,"name":"Item 0","subItems":[{"id":1,"name":"SubItem"}]}',
        { exact: false }
      )
    ).toBeVisible();

    await userEvent.click(screen.getByLabelText('close'));

    await waitFor(() => {
      expect(screen.queryByText('Update item')).not.toBeInTheDocument();
    });

    // The listing is back, without any dialog.
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByText('Item 0')).toBeVisible();
  });
});
