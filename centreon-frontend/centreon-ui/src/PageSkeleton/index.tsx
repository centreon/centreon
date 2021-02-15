import * as React from 'react';

import clsx from 'clsx';

import { makeStyles, useTheme } from '@material-ui/core';
import { Skeleton } from '@material-ui/lab';

import BaseRectSkeleton, { useSkeletonStyles } from './BaseSkeleton';
import ContentSkeleton from './ContentSkeleton';

const headerHeight = 6.5;
const footerHeight = 3.8;

const useStyles = makeStyles((theme) => ({
  skeletonContainer: {
    width: '100%',
    height: '100%',
  },
  menuContentContainer: {
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(5.5)}px 1fr`,
    height: '100%',
  },
  breadcrumbSkeleton: {
    margin: theme.spacing(0.5, 2),
    width: theme.spacing(30),
  },
  headerContentFooterContainer: {
    height: '100%',
    display: 'grid',
    gridTemplateRows: `auto ${theme.spacing(footerHeight)}`,
    alignContent: 'space-between',
    rowGap: `${theme.spacing(1)}px`,
  },
}));

export interface PageSkeletonProps {
  displayHeaderAndNavigation?: boolean;
  animate?: boolean;
}

const PageSkeleton = ({
  displayHeaderAndNavigation = false,
  animate = true,
}: PageSkeletonProps): JSX.Element => {
  const classes = useStyles();
  const skeletonClasses = useSkeletonStyles();
  const theme = useTheme();

  return (
    <div className={classes.skeletonContainer}>
      <div
        className={clsx({
          [classes.menuContentContainer]: displayHeaderAndNavigation,
        })}
      >
        <BaseRectSkeleton
          height="100%"
          animate={animate}
          width={`calc(100% - ${theme.spacing(0.5)}px)`}
        />
        <div className={classes.headerContentFooterContainer}>
          <div>
            {displayHeaderAndNavigation && (
              <BaseRectSkeleton
                height={theme.spacing(headerHeight)}
                animate={animate}
              />
            )}
            <Skeleton
              variant="text"
              className={clsx(
                classes.breadcrumbSkeleton,
                skeletonClasses.skeletonLayout,
              )}
              animation={animate ? 'wave' : false}
              height={theme.spacing(2.5)}
            />
            <ContentSkeleton animate={animate} />
          </div>
          {displayHeaderAndNavigation && (
            <BaseRectSkeleton
              height={theme.spacing(footerHeight)}
              animate={animate}
            />
          )}
        </div>
      </div>
    </div>
  );
};

export default PageSkeleton;
