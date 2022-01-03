import * as React from 'react';

import clsx from 'clsx';

import { Theme } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import WithPanel from '../Panel/WithPanel';

import FilterSkeleton from './FilterSkeleton';
import ListingSkeleton from './ListingSkeleton';

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
  filter: JSX.Element;
  listing: JSX.Element;
  pageClassName?: string;
  panel?: JSX.Element;
  panelFixed?: boolean;
  panelOpen?: boolean;
}

const ListingPage = ({
  listing,
  filter,
  panel,
  panelOpen = false,
  panelFixed = false,
  pageClassName,
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={clsx(classes.page, pageClassName)}>
      <div className={classes.filters}>
        <React.Suspense fallback={<FilterSkeleton />}>{filter}</React.Suspense>
      </div>

      <WithPanel fixed={panelFixed} open={panelOpen} panel={panel}>
        <div className={classes.listing}>
          <React.Suspense fallback={<ListingSkeleton />}>
            {listing}
          </React.Suspense>
        </div>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
