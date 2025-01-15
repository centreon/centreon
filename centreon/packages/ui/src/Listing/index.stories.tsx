/* eslint-disable no-alert */
/* eslint-disable react/prop-types */
import { useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';
import { equals, prop } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button } from '@mui/material';
import { grey } from '@mui/material/colors';

import { ListingVariant } from '@centreon/ui-context';

import { ListingProps } from '..';

import { Column, ColumnType, SortOrder } from './models';

import Listing from '.';

const meta: Meta<typeof Listing> = {
  argTypes: {
    checkable: { control: 'boolean' },
    currentPage: { control: 'number' },
    limit: { control: 'number' },
    loading: { control: 'boolean' },
    totalRows: { control: 'number' }
  },
  component: Listing,
  title: 'Listing'
};
export default meta;

type Story = StoryObj<typeof Listing>;

const useStyles = makeStyles()((theme) => ({
  listing: {
    backgroundColor: theme.palette.background.default,
    height: '100vh'
  }
}));

const ComponentColumn = ({ row, isSelected }): JSX.Element => (
  <div style={{ display: 'flex', flexDirection: 'row', flexWrap: 'wrap' }}>
    <span>
      {'I am '}
      <b>{`${isSelected ? 'selected' : 'not selected'}`}</b>
      {' / '}
    </span>
    <span>
      {'I am '}
      <b>{`${row.active ? 'active' : 'not active'}`}</b>
    </span>
  </div>
);

const ButtonColumn = ({ row }): JSX.Element => (
  <Button size="small" onClick={() => alert(JSON.stringify(row))}>
    Click to reveal details about {row.name}
  </Button>
);

const defaultColumns = [
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
    Component: ComponentColumn,
    id: '#',
    label: 'Custom',
    type: ColumnType.component
  }
];

const columnsWithShortLabel = [
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
    shortLabel: 'D',
    type: ColumnType.string
  },
  {
    Component: ComponentColumn,
    id: '#',
    label: 'Custom',
    type: ColumnType.component
  }
];

const tenElements = new Array(10).fill(0);

interface Entity {
  active: boolean;
  description: string;
  disableCheckbox: boolean;
  id: number;
  name: string;
  selected: boolean;
}

const listing = [...tenElements].map((_, index) => ({
  active: index % 2 === 0,
  description: `Entity ${index}`,
  disableCheckbox: index % 4 === 0,
  disableRow: index % 5 === 0,
  id: index,
  name: `E${index}`,
  selected: index % 3 === 0
}));

const rowColorConditions = [
  {
    color: grey[100],
    condition: ({ active }): boolean => !active,
    name: 'inactive'
  }
];

const predefinedRowsSelection = [
  {
    label: 'active',
    rowCondition: (row): boolean => row.active
  },
  {
    label: 'not active',
    rowCondition: (row): boolean => !row.active
  }
];

const StoryTemplate = ({
  columns = defaultColumns,
  checkable = true,
  viewerModeConfiguration,
  ...props
}: Omit<ListingProps<Entity>, 'columns'> & {
  columns?: Array<Column>;
}): JSX.Element => {
  const { classes } = useStyles();
  const [selected, setSelected] = useState<Array<Entity>>([]);

  return (
    <div className={classes.listing}>
      <Listing
        checkable={checkable}
        columns={columns}
        currentPage={0}
        disableRowCheckCondition={(row): boolean => row.disableCheckbox}
        disableRowCondition={(row): boolean => row.disableRow}
        limit={listing.length}
        predefinedRowsSelection={predefinedRowsSelection}
        rowColorConditions={rowColorConditions}
        rows={props.rows ?? listing}
        selectedRows={selected}
        totalRows={listing.length}
        viewerModeConfiguration={viewerModeConfiguration}
        onSelectRows={setSelected}
        {...props}
      />
    </div>
  );
};

export const normal = (): JSX.Element => <StoryTemplate />;

export const WithSpecifiedViewMode = (): JSX.Element => {
  const [listingVariant, setListingVariant] = useState(ListingVariant.extended);
  const newListingVariant = equals(listingVariant, ListingVariant.compact)
    ? ListingVariant.extended
    : ListingVariant.compact;

  return (
    <StoryTemplate
      listingVariant={listingVariant}
      viewerModeConfiguration={{
        onClick: () => setListingVariant(newListingVariant),
        title: listingVariant
      }}
    />
  );
};

export const loadingWithNoData = (): JSX.Element => {
  return <StoryTemplate loading rows={[]} totalRows={0} />;
};

export const loadingWithData = (): JSX.Element => {
  return <StoryTemplate loading />;
};

export const asEmptyState = (): JSX.Element => {
  return <StoryTemplate rows={[]} />;
};

const actions = (
  <Button color="primary" size="small" variant="contained">
    Action
  </Button>
);

export const withActions = (): JSX.Element => (
  <StoryTemplate actions={actions} />
);

export const withoutCheckboxes = (): JSX.Element => (
  <StoryTemplate checkable={false} />
);

export const withShortLabelColumns = (): JSX.Element => (
  <StoryTemplate columns={columnsWithShortLabel} />
);

const editableColumns = [
  {
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: 'Name',
    sortable: true,
    type: ColumnType.string
  },
  {
    getFormattedString: ({ description }): string => description,
    id: 'description',
    label: 'Description',
    sortable: true,
    type: ColumnType.string
  },
  {
    Component: ComponentColumn,
    id: '#',
    label: 'Custom',
    type: ColumnType.component
  },
  {
    disabled: true,
    getFormattedString: ({ name }): string => name,
    id: 'disabled_name',
    label: 'Disabled Name',
    type: ColumnType.string
  }
];

const ListingWithEditableColumns = (): JSX.Element => {
  const defaultColumnIds = defaultColumns.map(prop('id'));

  const [selectedColumnIds, setSelectedColumnIds] =
    useState<Array<string>>(defaultColumnIds);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultColumnIds);
  };

  const [sortedRows, setSortedRows] = useState(listing);
  const [sortParams, setSortParams] = useState({
    sortField: editableColumns[0].id,
    sortOrder: 'desc'
  });

  const onSort = (params: {
    sortField: string;
    sortOrder: SortOrder;
  }): void => {
    const rows = [...sortedRows];
    rows.sort((a, b) =>
      params.sortOrder === 'desc'
        ? a[params.sortField]?.localeCompare(b[params.sortField])
        : b[params.sortField]?.localeCompare(a[params.sortField])
    );
    setSortedRows(rows);
    setSortParams(params);
  };

  return (
    <StoryTemplate
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={editableColumns}
      rows={sortedRows}
      sortField={sortParams.sortField}
      sortOrder={sortParams.sortOrder as SortOrder}
      onResetColumns={resetColumns}
      onSelectColumns={setSelectedColumnIds}
      onSort={onSort}
    />
  );
};

export const withEditableAndSortableColumns = (): JSX.Element => (
  <ListingWithEditableColumns />
);

export const PlaygroundListing: Story = {
  args: {
    checkable: true,
    columns: editableColumns,
    currentPage: 1,
    disableRowCheckCondition: (row): boolean => row.disableCheckbox,
    disableRowCondition: (row): boolean => row.disableRow,
    limit: 10,
    loading: false,
    predefinedRowsSelection,
    rowColorConditions,
    rows: listing,
    totalRows: 10
  }
};

const listingWithSubItems = [...tenElements].map((_, index) => ({
  active: false,
  description: `Entity ${index}`,
  disableCheckbox: false,
  disableRow: false,
  id: index,
  name: `E${index}`,
  selected: false,
  subItems:
    index % 2 === 0
      ? [...tenElements].map((_, subIndex) => ({
          active: false,
          description: `Sub item ${subIndex + (index + 10) * 10} description`,
          disableCheckbox: false,
          disableRow: false,
          id: subIndex + (index + 10) * 10,
          name: `Sub Item ${subIndex + (index + 10) * 10}`,
          selected: false
        }))
      : undefined
}));

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

const TemplateSubItems = (args): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.listing}>
      <Listing {...args} />
    </div>
  );
};

export const ListingWithSubItems = {
  args: {
    checkable: true,
    columns: columnsWithSubItems,
    currentPage: 1,
    limit: 10,
    loading: false,
    rows: listingWithSubItems,
    subItems: {
      canCheckSubItems: false,
      enable: true,
      getRowProperty: () => 'subItems',
      labelCollapse: 'Collapse',
      labelExpand: 'Expand'
    },
    totalRows: 10
  },
  render: TemplateSubItems
};

export const ListingWithResponsive = {
  args: {
    checkable: true,
    columns: [
      ...defaultColumns,
      {
        Component: ComponentColumn,
        id: '##',
        label: 'Responsive',
        type: ColumnType.component,
        width: '140px'
      }
    ],
    currentPage: 1,
    isResponsive: true,
    limit: 10,
    loading: false,
    rows: listingWithSubItems,
    totalRows: 10
  },
  render: TemplateSubItems
};
