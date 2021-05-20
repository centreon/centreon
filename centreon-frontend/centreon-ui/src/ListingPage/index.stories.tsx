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
  autoComplete: {
    width: 250,
  },
  comment: {
    gridArea: 'comment',
  },
  description: {
    gridArea: 'description',
  },
  detailsContent: {
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateAreas: `
      'title title'
      'description comment'
    `,
    gridTemplateRows: '50px 100px',
  },
  detailsPanel: {
    display: 'grid',
    gridGap: theme.spacing(4),
    gridTemplateColumns: '94%',
    gridTemplateRows: 'auto 1fr',
    justifyContent: 'center',
    marginTop: theme.spacing(2),
  },
  detailsPanelHeader: {
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto min-content',
    justifyItems: 'center',
    margin: '0 auto',
    width: '95%',
  },
  filtersSummary: {
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto auto 1fr',
  },
  title: {
    alignItems: 'center',
    gridArea: 'title',
    textAlign: 'center',
  },
}));

const columns = [
  {
    getFormattedString: ({ name }): string => name,
    id: 'name',
    label: 'Name',
    type: ColumnType.string,
  },
  {
    getFormattedString: ({ description }): string => description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string,
  },
  {
    getFormattedString: ({ alias }): string => alias,
    id: 'alias',
    label: 'Alias',
    type: ColumnType.string,
  },
];

const twentyFiveElements = new Array(25).fill(0);

const elements = [...twentyFiveElements].map((_, index) => ({
  active: index % 2 === 0,
  alias: `Alias ${index}`,
  description: `Entity ${index}`,
  id: index,
  name: `E${index}`,
}));

const rowColorConditions = [
  {
    color: grey[500],
    condition: ({ active }): boolean => !active,
    name: 'inactive',
  },
];

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

const listing = (
  <Listing
    columns={columns}
    currentPage={0}
    limit={elements.length}
    rowColorConditions={rowColorConditions}
    rows={elements}
    totalRows={elements.length}
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
        label="Autocomplete"
        options={options}
        placeholder="Type here..."
        value={options[0]}
      />
      <AutocompleteField
        className={classes.autoComplete}
        label="Other Autocomplete"
        options={options}
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
      expandableFilters={<FiltersDetails />}
      expanded={expanded}
      filters={<FiltersSummary />}
      onExpand={() => setExpanded(!expanded)}
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
      <Typography align="center" variant="h5">
        Details Panel
      </Typography>
    </div>
  );
};

const DetailsPanel = ({
  onClose = () => undefined,
}: PanelProps): JSX.Element => (
  <Panel
    header={<DetailsPanelHeader />}
    selectedTab={<DetailsPanelContent />}
    onClose={onClose}
  />
);

const DetailsPanelWithTabs = (): JSX.Element => (
  <Panel
    header={<DetailsPanelHeader />}
    selectedTab={<DetailsPanelContent />}
    tabs={[<Tab key="tab1" label="Tab 1" />, <Tab key="tab2" label="Tab 2" />]}
  />
);

export const normal = (): JSX.Element => (
  <ListingPage
    filters={<NonExpandableFilters />}
    listing={listing}
    panelOpen={false}
  />
);

export const withOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    filters={<NonExpandableFilters />}
    listing={listing}
    panel={<DetailsPanel />}
  />
);

export const withOpenPanelAndTabs = (): JSX.Element => (
  <ListingPage
    panelOpen
    filters={<NonExpandableFilters />}
    listing={listing}
    panel={<DetailsPanelWithTabs />}
  />
);

export const withExpandableFilters = (): JSX.Element => (
  <ListingPage
    filters={<ExpandableFilters />}
    listing={listing}
    panelOpen={false}
  />
);

export const withFilterDetailsAndOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    filters={<ExpandableFilters />}
    listing={listing}
    panel={<DetailsPanel />}
  />
);

export const withExpandableFiltersAndFixedPanel = (): JSX.Element => {
  const [open, setOpen] = React.useState(true);
  return (
    <ListingPage
      panelFixed
      filters={<ExpandableFiltersWithOpenButton onOpen={() => setOpen(true)} />}
      listing={listing}
      panel={<DetailsPanel onClose={() => setOpen(false)} />}
      panelOpen={open}
    />
  );
};
