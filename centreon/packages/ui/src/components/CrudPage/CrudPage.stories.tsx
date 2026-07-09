import { FormControlLabel, Switch, Typography } from '@mui/material';

import type { Meta, StoryObj } from '@storybook/react';
import { atom, useAtom, useSetAtom } from 'jotai';
import { HttpResponse, http } from 'msw';
import { identity, isNil, prop } from 'ramda';
import { type ChangeEvent, useEffect } from 'react';

import { SnackbarProvider } from '../../';
import { type Column, ColumnType } from '../../Listing/models';
import { CrudPage } from '.';
import '../../ThemeProvider/tailwindcss.css';

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
      description: `Description ${idx}`,
      id: idx,
      name: `Item ${idx}`,
      subItems: [{ id: 1, name: 'SubItem' }]
    }));

const mockedListing = {
  meta: {
    limit: 30,
    page: 1,
    total: 60
  },
  result: generateItems(30)
};

const filtersAtom = atom<Filters>({
  hasDescription: true,
  isEven: false
});

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
    getFormattedString: prop('name'),
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: prop('description'),
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  }
];

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

const args = {
  baseEndpoint: '/listing',
  columns,
  deleteItem: {
    deleteEndpoint: (item) =>
      !isNil(item?.parent)
        ? `/delete/${item?.parent?.id}/subItems/${item?.id}`
        : `/delete/${item?.id}`,
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
  labels
};

const meta: Meta<typeof CrudPage<Item, Filters, Item, Item>> = {
  args,
  component: CrudPage<Item, Filters, Item, Item>,
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json(mockedListing);
        }),
        http.get('**/item**', () => {
          return HttpResponse.json({
            description: 'Description 0',
            id: 0,
            name: 'Item 0',
            subItems: [{ id: 1, name: 'SubItem' }]
          });
        }),
        http.delete('**/delete**', () => {
          return HttpResponse.json({});
        })
      ]
    }
  },
  render: (args) => {
    return (
      <SnackbarProvider>
        <div style={{ height: '90vh' }}>
          <CrudPage<Item, Filters, Item, Item> {...args} />
        </div>
      </SnackbarProvider>
    );
  }
};

export default meta;
type Story = StoryObj<typeof CrudPage<Item, Filters>>;

export const Default: Story = {
  args: {
    queryKeyName: 'default'
  },
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json({
            meta: {
              limit: 30,
              page: 1,
              total: 60
            },
            result: []
          });
        })
      ]
    }
  }
};

export const WithItems: Story = {
  args: {
    queryKeyName: 'withItems'
  }
};

export const WithSubItem: Story = {
  args: {
    queryKeyName: 'subItems',
    subItems: {
      canCheckSubItems: false,
      enable: true,
      getRowProperty: () => 'subItems',
      labelCollapse: 'Collapse',
      labelExpand: 'Expand'
    }
  }
};
