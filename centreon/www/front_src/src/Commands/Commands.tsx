import { Typography } from '@mui/material';

import { identity, isNil } from 'ramda';

import { CrudPage, CrudPageRootProps } from '@centreon/ui/components';

import { columns } from './Columns/Columns';
import Filters from './Filters/Filters';
import Form from './Form/Form';

import { filtersAtom } from './atoms';
import { Filters as FiltersType } from './models';

import { commandsEndpoint, getCommandEndpoint } from './api/endpoint';
import {
  labelAddCommand,
  labelCencel,
  labelCommands,
  labelModifyCommand,
  labelSave,
  labelSearch,
  labelWelcomePageDescription,
  labelWelcomePageTitle
} from './translatedLabels';

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
  title: labelCommands,
  welcome: {
    title: labelWelcomePageTitle,
    description: labelWelcomePageDescription
  },
  actions: {
    create: labelAddCommand
  },
  listing: {
    search: labelSearch
  }
};

const defaultProps: CrudPageRootProps<Item, FiltersType, Item, Item> = {
  baseEndpoint: commandsEndpoint,
  queryKeyName: 'listCommands',
  filtersAtom,
  getSearchParameters,
  labels,
  columns,
  filters: <Filters />,
  form: {
    modalSize: 'xlarge',
    getItem: {
      baseEndpoint: getCommandEndpoint,
      adapter: identity,
      itemQueryKey: 'getCommand'
    },
    Form,
    labels: {
      add: {
        title: labelAddCommand,
        cancel: labelCencel,
        confirm: labelSave
      },
      update: {
        title: labelModifyCommand,
        cancel: labelCencel,
        confirm: labelSave
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
