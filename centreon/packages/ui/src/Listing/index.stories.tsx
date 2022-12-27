/* eslint-disable react/prop-types */
import { React, useState } from 'react';

import { ComponentMeta, ComponentStory } from '@storybook/react';
import { equals, prop } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button } from '@mui/material';
import { grey } from '@mui/material/colors';

import { ResourceStatusViewMode } from '@centreon/ui-context/src';

import { ListingProps } from '..';

import { Column, ColumnType } from './models';

import Listing from '.';

export default {
  argTypes: {
    checkable: { control: 'boolean' },
    currentPage: { control: 'number' },
    limit: { control: 'number' },
    loading: { control: 'boolean' },
    totalRows: { control: 'number' }
  },
  component: Listing,
  title: 'Listing'
} as ComponentMeta<typeof Listing>;

const useStyles = makeStyles()((theme) => ({
  listing: {
    backgroundColor: theme.palette.background.default
  }
}));

const ComponentColumn = ({ row, isSelected }): JSX.Element => (
  <>
    <span>
      {'I am '}
      <b>{`${isSelected ? 'selected' : 'not selected'}`}</b>
      {' / '}
    </span>
    <span>
      {'I am '}
      <b>{`${row.active ? 'active' : 'not active'}`}</b>
    </span>
  </>
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

const Story = ({
  columns = defaultColumns,
  checkable = true,
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
        rows={listing}
        selectedRows={selected}
        totalRows={listing.length}
        onSelectRows={setSelected}
        {...props}
      />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;

export const WithViewModeExtended = (): JSX.Element => {
  const [viewMode, setViewMode] = useState(ResourceStatusViewMode.extended);
  const newViewMode = equals(viewMode, ResourceStatusViewMode.compact)
    ? ResourceStatusViewMode.extended
    : ResourceStatusViewMode.compact;

  return (
    <>
      <Button
        color="primary"
        size="small"
        variant="contained"
        onClick={(): void => setViewMode(newViewMode)}
      >
        Change view mode
      </Button>
      <Story viewMode={viewMode} />
    </>
  );
};

export const loadingWithNoData = (): JSX.Element => {
  return <Story loading rows={[]} totalRows={0} />;
};

export const loadingWithData = (): JSX.Element => {
  return <Story loading />;
};

const actions = (
  <Button color="primary" size="small" variant="contained">
    Action
  </Button>
);

export const withActions = (): JSX.Element => <Story actions={actions} />;

export const withoutCheckboxes = (): JSX.Element => <Story checkable={false} />;

export const withShortLabelColumns = (): JSX.Element => (
  <Story columns={columnsWithShortLabel} />
);

const editableColumns = [
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

  return (
    <Story
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={editableColumns}
      onResetColumns={resetColumns}
      onSelectColumns={setSelectedColumnIds}
    />
  );
};

export const withEditableColumns = (): JSX.Element => (
  <ListingWithEditableColumns />
);

const TemplateListing: ComponentStory<typeof Listing> = (args) => (
  <Listing
    {...args}
    columns={editableColumns}
    disableRowCheckCondition={(row): boolean => row.disableCheckbox}
    disableRowCondition={(row): boolean => row.disableRow}
    predefinedRowsSelection={predefinedRowsSelection}
    rowColorConditions={rowColorConditions}
    rows={listing}
  />
);

export const PlaygroundListing = TemplateListing.bind({});
PlaygroundListing.args = {
  checkable: true,
  currentPage: 1,
  limit: 10,
  loading: false,
  totalRows: 10
};
