import { FormControlLabel, Switch, Typography } from '@mui/material';

import { atom, createStore, Provider, useAtom, useSetAtom } from 'jotai';
import { identity, isNil } from 'ramda';
import { type ChangeEvent, useEffect } from 'react';

import {
  type Column,
  ColumnType,
  SnackbarProvider,
  TestQueryProvider
} from '../..';
import { CrudPage } from '.';
import { CrudPageRoot } from './CrudPageRoot';
import type { CrudPageRootProps } from './models';

interface Item {
  description: string;
  id: number;
  name: string;
  subItems: Array<{ id: number; name: string }>;
}

interface Filters {
  hasDescription: boolean;
  isEven: boolean;
}

const filtersAtom = atom<Filters>({ hasDescription: true, isEven: false });

const FiltersForm = (): JSX.Element => {
  const [filters, setFilters] = useAtom(filtersAtom);
  const change =
    (property: keyof Filters) => (event: ChangeEvent<HTMLInputElement>) =>
      setFilters((current) => ({
        ...current,
        [property]: event.target.checked
      }));

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

const columns: Array<Column> = [
  {
    displaySubItemsCaret: true,
    getFormattedString: (row) => String(row.name),
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: (row) => String(row.description),
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  }
];

const Form = ({ initialValues }: { initialValues?: Item }): JSX.Element => {
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
};

const advancedLabels = {
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
  title: (item) => (!isNil(item?.parent) ? 'Delete sub item' : 'Delete item')
};

const basicLabels = {
  cancel: 'Cancel',
  confirm: 'Delete',
  description: 'A small description',
  successMessage: 'This is a success',
  title: 'Title'
};

interface Props {
  basicLabels?: boolean;
  empty?: boolean;
  /** Sub items shown, deletable or not. */
  subItems?: 'deletable' | 'none' | 'undeletable';
}

export const Default = ({
  basicLabels: basic = false,
  empty = false,
  subItems = 'none'
}: Props): JSX.Element => {
  const props: CrudPageRootProps<Item, Filters, Item, Item> = {
    baseEndpoint: empty ? '/empty-listing' : '/listing',
    columns,
    deleteItem: {
      deleteEndpoint: (item) =>
        !isNil(item?.parent)
          ? `/listing/${item?.parent?.id}/subItems/${item?.id}`
          : `/listing/${item?.id}`,
      labels: (basic ? basicLabels : advancedLabels) as never
    },
    filters: <FiltersForm />,
    filtersAtom,
    form: {
      Form,
      getItem: {
        adapter: identity,
        baseEndpoint: (id) => `/item/${id}`,
        itemQueryKey: 'item'
      },
      labels: {
        add: { cancel: 'Cancel', confirm: 'Add', title: 'Add item' },
        update: { cancel: 'Cancel', confirm: 'Update', title: 'Update item' }
      }
    },
    getSearchParameters: ({ filters }) => ({
      conditions: [
        // Booleans, as the Cypress spec sent, though typed as strings.
        { field: 'hasDescription', values: { $in: filters.hasDescription as never } },
        { field: 'isEven', values: { $in: filters.isEven as never } }
      ]
    }),
    labels: {
      actions: { create: 'Create item' },
      listing: { search: 'Search' },
      title: 'Items',
      welcome: {
        description: 'This page handles item',
        title: 'Welcome to the items page'
      }
    },
    queryKeyName: empty ? 'empty-listing' : 'listing',
    subItems:
      subItems === 'none'
        ? undefined
        : {
            canCheckSubItems: false,
            canDeleteSubItems: subItems === 'deletable',
            enable: true,
            getRowProperty: () => 'subItems',
            labelCollapse: 'Collapse',
            labelExpand: 'Expand'
          }
  };

  return (
    <div style={{ height: '95vh' }}>
      <Provider store={createStore()}>
        <SnackbarProvider>
          <TestQueryProvider>
            <CrudPageRoot {...props} />
          </TestQueryProvider>
        </SnackbarProvider>
      </Provider>
    </div>
  );
};
