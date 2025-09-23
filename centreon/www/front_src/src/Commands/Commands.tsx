import { Typography } from '@mui/material';
import { useAtom, useSetAtom } from 'jotai';
import { identity, isNil } from 'ramda';
import { useEffect } from 'react';

import { CrudPage, CrudPageRootProps } from '@centreon/ui/components';
import { columns } from './Columns/Columns';
import Filters from './Filters/Filters';

import { filtersAtom } from './atoms';
import { Filters as FiltersType } from './models';

interface Item {
  id: number;
  name: string;
  description: string;
  subItems: Array<{ id: number; name: string }>;
}

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

const defaultProps: CrudPageRootProps<Item, FiltersType, Item, Item> = {
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

const Commands = () => {
  return <CrudPage {...defaultProps} />;
};

export default Commands;
