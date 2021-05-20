import * as React from 'react';

import { makeStyles, Theme } from '@material-ui/core';

import WithPanel from '../Panel/WithPanel';

const useStyles = makeStyles<Theme>((theme) => {
  return {
    filters: {
      zIndex: 4,
    },
    listing: {
      height: '100%',
      marginLeft: theme.spacing(2),
      marginRight: theme.spacing(2),
    },
    page: {
      backgroundColor: theme.palette.background.default,
      display: 'grid',
      gridTemplateRows: 'auto 1fr',
      height: '100%',
      overflow: 'hidden',
    },
  };
});

interface Props {
  filters: JSX.Element;
  listing: JSX.Element;
  panel?: JSX.Element;
  panelFixed?: boolean;
  panelOpen?: boolean;
}

const ListingPage = ({
  listing,
  filters,
  panel,
  panelOpen = false,
  panelFixed = false,
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.page}>
      <div className={classes.filters}>{filters}</div>

      <WithPanel fixed={panelFixed} open={panelOpen} panel={panel}>
        <div className={classes.listing}>{listing}</div>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
