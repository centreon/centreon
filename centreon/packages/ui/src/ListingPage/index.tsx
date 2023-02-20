import { Suspense, useEffect, useRef, useState } from 'react';

import clsx from 'clsx';

import { Box, Theme } from '@mui/material';
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
  listingScrollOffset?: number;
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
  listingScrollOffset = 16,
}: Props): JSX.Element => {
  const classes = useStyles();
  const [listingHeight, setListingHeight] = useState(0);
  const listingRef = useRef<HTMLDivElement | null>(null);
  const filtersRef = useRef<HTMLDivElement | null>(null);

  const resize = (): void => {
    setListingHeight(window.innerHeight);
  };

  useEffect(() => {
    window.addEventListener('resize', resize);

    setListingHeight(window.innerHeight);

    return () => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  const listingContainerHeight =
    listingHeight -
    (filtersRef.current?.getBoundingClientRect().height || 0) -
    (filtersRef.current?.getBoundingClientRect().top || 0) -
    listingScrollOffset;

  return (
    <div className={clsx(classes.page, pageClassName)}>
      <div className={classes.filters} ref={filtersRef}>
        <Suspense fallback={<FilterSkeleton />}>{filter}</Suspense>
      </div>

      <WithPanel fixed={panelFixed} open={panelOpen} panel={panel}>
        <Box
          className={classes.listing}
          ref={listingRef}
          sx={{
            maxHeight: listingContainerHeight,
            overflowY: 'auto',
          }}
        >
          <Suspense fallback={<ListingSkeleton />}>{listing}</Suspense>
        </Box>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
