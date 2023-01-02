import { Suspense, useEffect, useRef, useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import useMemoComponent from '../utils/useMemoComponent';
import WithPanel from '../Panel/WithPanel';

import FilterSkeleton from './FilterSkeleton';
import ListingSkeleton from './ListingSkeleton';

const useStyles = makeStyles()((theme) => {
  return {
    filters: {
      borderBottom: `1px solid ${theme.palette.divider}`,
      margin: theme.spacing(0, 3)
    },
    listing: {
      margin: theme.spacing(0, 3),
      overflowY: 'auto',
      paddingTop: theme.spacing(1)
    },
    page: {
      display: 'grid',
      gridTemplateRows: 'auto 1fr',
      height: '100%',
      overflow: 'hidden'
    }
  };
});

interface Props {
  filter: JSX.Element;
  fullHeight?: boolean;
  listing: JSX.Element;
  listingScrollOffset?: number;
  memoListingProps?: Array<unknown>;
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
  fullHeight = false,
  memoListingProps = []
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

  const memoListingComponent = useMemoComponent({
    Component: listing,
    memoProps: [...memoListingProps]
  });

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
            ...(fullHeight && { height: '100%' }),
            maxHeight: listingContainerHeight
          }}
        >
          <Suspense fallback={<ListingSkeleton />}>
            {memoListingComponent}
          </Suspense>
        </Box>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
