import { Suspense, useRef } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { ParentSize } from '..';
import WithPanel from '../Panel/WithPanel';
import { useMemoComponent } from '../utils';

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

export interface ListingPageProps {
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
}: ListingPageProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const filtersRef = useRef<HTMLDivElement | null>(null);

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
        <ParentSize>
          {({ height }) => (
            <Box
              className={classes.listing}
              sx={{
                height: `calc(${height}px - ${listingScrollOffset}px)`,
                ...(fullHeight && { height: '100%' })
              }}
            >
              <Suspense fallback={<ListingSkeleton />}>
                {memoListingComponent}
              </Suspense>
            </Box>
          )}
        </ParentSize>
      </WithPanel>
    </div>
  );
};

export default ListingPage;
