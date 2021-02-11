/* eslint-disable react-hooks/rules-of-hooks */

import * as React from 'react';

import { Typography, makeStyles, Paper, Button, Tab } from '@material-ui/core';
import { grey } from '@material-ui/core/colors';

import Listing from '../Listing';
import { ColumnType } from '../Listing/models';
import { SearchField } from '..';
import TextField from '../InputField/Text';
import AutocompleteField from '../InputField/Select/Autocomplete';
import Panel from '../Panel';

import Filters from './Filters';

import ListingPage from '.';

export default { title: 'Listing Page' };

const useStyles = makeStyles((theme) => ({
  filtersSummary: {
    display: 'grid',
    gridTemplateColumns: 'auto auto 1fr',
    gridGap: theme.spacing(2),
    alignItems: 'center',
  },
  autoComplete: {
    width: 250,
  },
  detailsPanel: {
    display: 'grid',
    gridTemplateRows: 'auto 1fr',
    gridTemplateColumns: '94%',
    justifyContent: 'center',
    marginTop: theme.spacing(2),
    gridGap: theme.spacing(4),
  },
  detailsContent: {
    display: 'grid',
    gridTemplateAreas: `
      'title title'
      'description comment'
    `,
    gridTemplateRows: '50px 100px',
    gridGap: theme.spacing(2),
  },
  title: {
    gridArea: 'title',
    alignItems: 'center',
    textAlign: 'center',
  },
  description: {
    gridArea: 'description',
  },
  comment: {
    gridArea: 'comment',
  },
  detailsPanelHeader: {
    display: 'grid',
    gridTemplateColumns: 'auto min-content',
    gridGap: theme.spacing(2),
    margin: '0 auto',
    justifyItems: 'center',
    width: '95%',
  },
}));

const configuration = [
  {
    id: 'name',
    label: 'Name',
    type: ColumnType.string,
    getFormattedString: ({ name }): string => name,
  },
  {
    id: 'description',
    label: 'Description',
    type: ColumnType.string,
    getFormattedString: ({ description }): string => description,
  },
  {
    id: 'alias',
    label: 'Alias',
    type: ColumnType.string,
    getFormattedString: ({ alias }): string => alias,
  },
];

const twentyFiveElements = new Array(25).fill(0);

const elements = [...twentyFiveElements].map((_, index) => ({
  id: index,
  name: `E${index}`,
  description: `Entity ${index}`,
  alias: `Alias ${index}`,
  active: index % 2 === 0,
}));

const rowColorConditions = [
  {
    name: 'inactive',
    condition: ({ active }): boolean => !active,
    color: grey[500],
  },
];

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

const listing = (
  <Listing
    columnConfiguration={configuration}
    limit={elements.length}
    currentPage={0}
    totalRows={elements.length}
    tableData={elements}
    rowColorConditions={rowColorConditions}
  />
);

const FiltersSummary = (): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.filtersSummary}>
      <Typography>Filters</Typography>
      <SearchField />
    </div>
  );
};

const NonExpandableFilters = (): JSX.Element => {
  return <Filters filters={<FiltersSummary />} />;
};

interface ExpandableFiltersWithOpenButtonProps {
  onOpen?: () => void;
}

const ExpandableFiltersWithOpenButton = ({
  onOpen = () => undefined,
}: ExpandableFiltersWithOpenButtonProps): JSX.Element => {
  const classes = useStyles();

  return (
    <Filters
      filters={
        <div className={classes.filtersSummary}>
          <Typography>Filters</Typography>
          <SearchField />
          <Button onClick={onOpen}>Open Side Panel</Button>
        </div>
      }
    />
  );
};
const FiltersDetails = (): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.filtersSummary}>
      <TextField placeholder="Text" />
      <AutocompleteField
        className={classes.autoComplete}
        options={options}
        label="Autocomplete"
        placeholder="Type here..."
        value={options[0]}
      />
      <AutocompleteField
        className={classes.autoComplete}
        options={options}
        label="Other Autocomplete"
        placeholder="Type here..."
        value={options[1]}
      />
      <TextField placeholder="Other Text" />
    </div>
  );
};

const ExpandableFilters = (): JSX.Element => {
  const [expanded, setExpanded] = React.useState(false);

  return (
    <Filters
      filters={<FiltersSummary />}
      expanded={expanded}
      onExpand={() => setExpanded(!expanded)}
      expandableFilters={<FiltersDetails />}
    />
  );
};

const DetailsPanelContent = (): JSX.Element => {
  const classes = useStyles();
  return (
    <div className={classes.detailsPanel}>
      <div className={classes.detailsContent}>
        <Paper className={classes.title}>
          <Typography>My Title</Typography>
        </Paper>
        <Paper className={classes.description}>
          <Typography>Description</Typography>
        </Paper>
        <Paper className={classes.comment}>
          <Typography>Comment</Typography>
        </Paper>
      </div>
      <div className={classes.detailsContent}>
        <Paper className={classes.title}>
          <Typography>My Title</Typography>
        </Paper>
        <Paper className={classes.description}>
          <Typography>Description</Typography>
        </Paper>
        <Paper className={classes.comment}>
          <Typography>Comment</Typography>
        </Paper>
      </div>
      <div className={classes.detailsContent}>
        <Paper className={classes.title}>
          <Typography>My Title</Typography>
        </Paper>
        <Paper className={classes.description}>
          <Typography>Description</Typography>
        </Paper>
        <Paper className={classes.comment}>
          <Typography>Comment</Typography>
        </Paper>
      </div>
      <div className={classes.detailsContent}>
        <Paper className={classes.title}>
          <Typography>My Title</Typography>
        </Paper>
        <Paper className={classes.description}>
          <Typography>Description</Typography>
        </Paper>
        <Paper className={classes.comment}>
          <Typography>Comment</Typography>
        </Paper>
      </div>
      <div className={classes.detailsContent}>
        <Paper className={classes.title}>
          <Typography>My Title</Typography>
        </Paper>
        <Paper className={classes.description}>
          <Typography>Description</Typography>
        </Paper>
        <Paper className={classes.comment}>
          <Typography>Comment</Typography>
        </Paper>
      </div>
    </div>
  );
};

interface PanelProps {
  onClose?: () => void;
}

const DetailsPanelHeader = (): JSX.Element => {
  const classes = useStyles();
  return (
    <div className={classes.detailsPanelHeader}>
      <Typography variant="h5" align="center">
        Details Panel
      </Typography>
    </div>
  );
};

const DetailsPanel = ({
  onClose = () => undefined,
}: PanelProps): JSX.Element => (
  <Panel
    onClose={onClose}
    header={<DetailsPanelHeader />}
    selectedTab={<DetailsPanelContent />}
  />
);

const DetailsPanelWithTabs = (): JSX.Element => (
  <Panel
    header={<DetailsPanelHeader />}
    tabs={[<Tab key="tab1" label="Tab 1" />, <Tab key="tab2" label="Tab 2" />]}
    selectedTab={<DetailsPanelContent />}
  />
);

export const normal = (): JSX.Element => (
  <ListingPage
    panelOpen={false}
    listing={listing}
    filters={<NonExpandableFilters />}
  />
);

export const withOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    listing={listing}
    filters={<NonExpandableFilters />}
    panel={<DetailsPanel />}
  />
);

export const withOpenPanelAndTabs = (): JSX.Element => (
  <ListingPage
    panelOpen
    listing={listing}
    filters={<NonExpandableFilters />}
    panel={<DetailsPanelWithTabs />}
  />
);

export const withExpandableFilters = (): JSX.Element => (
  <ListingPage
    panelOpen={false}
    listing={listing}
    filters={<ExpandableFilters />}
  />
);

export const withFilterDetailsAndOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    listing={listing}
    filters={<ExpandableFilters />}
    panel={<DetailsPanel />}
  />
);

export const withExpandableFiltersAndFixedPanel = (): JSX.Element => {
  const [open, setOpen] = React.useState(true);
  return (
    <ListingPage
      panelOpen={open}
      listing={listing}
      filters={<ExpandableFiltersWithOpenButton onOpen={() => setOpen(true)} />}
      panel={<DetailsPanel onClose={() => setOpen(false)} />}
      panelFixed
    />
  );
};
