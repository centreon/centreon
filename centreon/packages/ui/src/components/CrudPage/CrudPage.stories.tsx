import { FormControlLabel, Switch } from '@mui/material';
import { Meta, StoryObj } from '@storybook/react';
import { atom, useAtom } from 'jotai';
import { http, HttpResponse } from 'msw';
import { prop } from 'ramda';
import { ChangeEvent } from 'react';
import { CrudPage } from '.';
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
  filters: <Filters />
};

const meta: Meta<typeof CrudPage<Item, Filters>> = {
  args,
  component: CrudPage<Item, Filters>,
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json(mockedListing);
        })
      ]
    }
  },
  render: (args) => {
    return (
      <div style={{ height: '90vh' }}>
        <CrudPage<Item, Filters> {...args} />
      </div>
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
