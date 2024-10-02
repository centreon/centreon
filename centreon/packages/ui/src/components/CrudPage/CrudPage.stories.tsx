import { Meta, StoryObj } from '@storybook/react';
import { atom } from 'jotai';
import { http, HttpResponse } from 'msw';
import { prop } from 'ramda';
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

const meta: Meta<typeof CrudPage<Item, Filters>> = {
  component: CrudPage<Item, Filters>,
  render(args) {
    return (
      <div style={{ height: '90vh' }}>
        <CrudPage<Item, Filters> {...args} />
      </div>
    );
  }
};

export default meta;
type Story = StoryObj<typeof CrudPage<Item, Filters>>;

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

export const Default: Story = {
  args: {
    baseEndpoint: '/listing',
    queryKeyName: 'items',
    filtersAtom,
    getSearchParameters,
    labels,
    columns
  },
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json(mockedListing);
        })
      ]
    }
  }
};

export const WithSubItem: Story = {
  args: {
    baseEndpoint: '/listing',
    queryKeyName: 'items',
    filtersAtom,
    getSearchParameters,
    labels,
    columns,
    subItems: {
      canCheckSubItems: false,
      enable: true,
      getRowProperty: () => 'subItems',
      labelExpand: 'Expand',
      labelCollapse: 'Collapse'
    }
  },
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json(mockedListing);
        })
      ]
    }
  }
};
