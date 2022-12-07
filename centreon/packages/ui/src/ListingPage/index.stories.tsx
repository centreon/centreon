/* eslint-disable react-hooks/rules-of-hooks */

import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Button, Paper, Tab, Typography } from '@mui/material';
import { grey } from '@mui/material/colors';

import { SearchField } from '..';
import Listing from '../Listing';
import { ColumnType } from '../Listing/models';
import Panel from '../Panel';

import Filter from './Filter';

import ListingPage from '.';

export default { title: 'Listing Page' };

const useStyles = makeStyles()((theme) => ({
  autoComplete: {
    width: 250
  },
  comment: {
    gridArea: 'comment'
  },
  description: {
    gridArea: 'description'
  },
  detailsContent: {
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateAreas: `
      'title title'
      'description comment'
    `,
    gridTemplateRows: '50px 100px'
  },
  detailsPanel: {
    display: 'grid',
    gridGap: theme.spacing(4),
    gridTemplateColumns: '94%',
    gridTemplateRows: 'auto 1fr',
    justifyContent: 'center',
    marginTop: theme.spacing(2)
  },
  detailsPanelHeader: {
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto min-content',
    justifyItems: 'center',
    margin: '0 auto',
    width: '95%'
  },
  filterSummary: {
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto auto 1fr'
  },
  title: {
    alignItems: 'center',
    gridArea: 'title',
    textAlign: 'center'
  }
}));

const columns = [
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
    getFormattedString: ({ alias }): string => alias,
    id: 'alias',
    label: 'Alias',
    type: ColumnType.string
  }
];

const twentyFiveElements = new Array(25).fill(0);

const elements = [...twentyFiveElements].map((_, index) => ({
  active: index % 2 === 0,
  alias: `Alias ${index}`,
  description: `Entity ${index}`,
  id: index,
  name: `E${index}`
}));

const rowColorConditions = [
  {
    color: grey[500],
    condition: ({ active }): boolean => !active,
    name: 'inactive'
  }
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

const FilterSummary = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.filterSummary}>
      <Typography>Filter</Typography>
      <SearchField />
    </div>
  );
};

const FilterWithContent = (): JSX.Element => {
  return <Filter content={<FilterSummary />} />;
};

interface FilterWithOpenButton {
  onOpen?: () => void;
}

const FilterWithOpenButton = ({
  onOpen = (): undefined => undefined
}: FilterWithOpenButton): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Filter
      content={
        <div className={classes.filterSummary}>
          <Typography>Filter</Typography>
          <SearchField />
          <Button onClick={onOpen}>Open Side Panel</Button>
        </div>
      }
    />
  );
};

const DetailsPanelContent = (): JSX.Element => {
  const { classes } = useStyles();

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
  const { classes } = useStyles();

  return (
    <div className={classes.detailsPanelHeader}>
      <Typography align="center" variant="h5">
        Details Panel
      </Typography>
    </div>
  );
};

const DetailsPanel = ({
  onClose = (): undefined => undefined
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
    filter={<FilterWithContent />}
    listing={listing}
    panelOpen={false}
  />
);

export const withOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    filter={<FilterWithContent />}
    listing={listing}
    panel={<DetailsPanel />}
  />
);

export const withOpenPanelAndTabs = (): JSX.Element => (
  <ListingPage
    panelOpen
    filter={<FilterWithContent />}
    listing={listing}
    panel={<DetailsPanelWithTabs />}
  />
);

export const withFilterDetailsAndOpenPanel = (): JSX.Element => (
  <ListingPage
    panelOpen
    filter={<FilterWithContent />}
    listing={listing}
    panel={<DetailsPanel />}
  />
);

export const withFixedPanel = (): JSX.Element => {
  const [open, setOpen] = useState(true);

  return (
    <ListingPage
      panelFixed
      filter={<FilterWithOpenButton onOpen={(): void => setOpen(true)} />}
      listing={listing}
      panel={<DetailsPanel onClose={(): void => setOpen(false)} />}
      panelOpen={open}
    />
  );
};

export const withResponsivePaginationTable = (): JSX.Element => {
  const [open, setOpen] = useState(true);

  return (
    <ListingPage
      filter={<FilterWithOpenButton onOpen={(): void => setOpen(true)} />}
      listing={
        <Listing
          columns={columns}
          currentPage={0}
          limit={elements.length}
          moveTablePagination={open}
          rowColorConditions={rowColorConditions}
          rows={elements}
          totalRows={elements.length}
          widthToMoveTablePagination={550}
        />
      }
      memoListingProps={[open]}
      panel={<DetailsPanel onClose={(): void => setOpen(false)} />}
      panelOpen={open}
    />
  );
};

export const withALongContent = (): JSX.Element => (
  <ListingPage
    filter={<FilterWithContent />}
    listing={
      <div>
        <Typography variant="h1">It</Typography>
        <Typography variant="h1">is</Typography>
        <Typography variant="h1">displaying</Typography>
        <Typography variant="h1">something</Typography>
        <Typography variant="h1">like</Typography>
        <Typography variant="h1">a</Typography>
        <Typography variant="h1">long</Typography>
        <Typography variant="h1">body</Typography>
        <Typography variant="h1">content</Typography>
      </div>
    }
    panelOpen={false}
  />
);
