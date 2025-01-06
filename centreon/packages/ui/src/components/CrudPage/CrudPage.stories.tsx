import { FormControlLabel, Switch, Typography } from '@mui/material';
import { Meta, StoryObj } from '@storybook/react';
import { atom, useAtom, useSetAtom } from 'jotai';
import { http, HttpResponse } from 'msw';
import { identity, isNil, prop } from 'ramda';
import { ChangeEvent, useEffect } from 'react';
import { CrudPage } from '.';
import { SnackbarProvider } from '../../';
import { Column, ColumnType } from '../../Listing/models';

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

const mockedListing = {
  result: generateItems(30),
  meta: {
    page: 1,
    total: 60,
    limit: 30
  }
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
        ? `/delete/${item?.parent?.id}/subItems/${item?.id}`
        : `/delete/${item?.id}`,
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
            id: 0,
            name: 'Item 0',
            description: 'Description 0',
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
            result: [],
            meta: {
              page: 1,
              total: 60,
              limit: 30
            }
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
      labelExpand: 'Expand',
      labelCollapse: 'Collapse'
    }
  }
};
