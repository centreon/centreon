import { Suspense, useEffect, useRef, useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import WithPanel from '../Panel/WithPanel';

import FilterSkeleton from './FilterSkeleton';
import ListingSkeleton from './ListingSkeleton';

const useStyles = makeStyles()((theme) => {
  return {
    filters: {
      margin: theme.spacing(0, 4, 0, 3),
      zIndex: 4,
    },
    listing: {
      margin: theme.spacing(0, 4, 0, 3),
    },
    page: {
      backgroundColor: theme.palette.background.paper,
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
  const { classes, cx } = useStyles();
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
    <div className={cx(classes.page, pageClassName)}>
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
