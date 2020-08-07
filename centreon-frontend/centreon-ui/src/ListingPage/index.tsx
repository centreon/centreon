import * as React from 'react';

import { makeStyles, Theme } from '@material-ui/core';
import WithPanel from '../Panel/WithPanel';

const useStyles = makeStyles<Theme>((theme) => {
  return {
    page: {
      height: '100%',
      display: 'grid',
      gridTemplateRows: 'auto 1fr',
      backgroundColor: theme.palette.background.default,
      overflowY: 'hidden',
    },
    filters: {
      zIndex: 4,
    },
    listing: {
      marginLeft: theme.spacing(2),
      marginRight: theme.spacing(2),
      height: '100%',
    },
  };
});

interface Props {
  filters: JSX.Element;
  listing: JSX.Element;
  panel?: JSX.Element;
  panelOpen?: boolean;
  panelFixed?: boolean;
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

      <WithPanel open={panelOpen} panel={panel} fixed={panelFixed}>
        <div className={classes.listing}>{listing}</div>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
