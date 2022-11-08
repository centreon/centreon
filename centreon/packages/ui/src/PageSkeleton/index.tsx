import * as React from 'react';

import clsx from 'clsx';

import { makeStyles, useTheme } from '@material-ui/core';
import { Skeleton } from '@material-ui/lab';

import BaseRectSkeleton, { useSkeletonStyles } from './BaseSkeleton';
import ContentSkeleton from './ContentSkeleton';

const headerHeight = 6.5;
const footerHeight = 3.8;

const useStyles = makeStyles((theme) => ({
  breadcrumbSkeleton: {
    margin: theme.spacing(0.5, 2),
    width: theme.spacing(30),
  },
  headerContentFooterContainer: {
    alignContent: 'space-between',
    display: 'grid',
    gridTemplateRows: `auto ${theme.spacing(footerHeight)}`,
    height: '100%',
    rowGap: `${theme.spacing(1)}px`,
  },
  menuContentContainer: {
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(5.5)}px 1fr`,
    height: '100%',
  },
  skeletonContainer: {
    height: '100%',
    width: '100%',
  },
}));

export interface PageSkeletonProps {
  animate?: boolean;
  displayHeaderAndNavigation?: boolean;
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
          animate={animate}
          height="100%"
          width={`calc(100% - ${theme.spacing(0.5)}px)`}
        />
        <div className={classes.headerContentFooterContainer}>
          <div>
            {displayHeaderAndNavigation && (
              <BaseRectSkeleton
                animate={animate}
                height={theme.spacing(headerHeight)}
              />
            )}
            <Skeleton
              animation={animate ? 'wave' : false}
              className={clsx(
                classes.breadcrumbSkeleton,
                skeletonClasses.skeletonLayout,
              )}
              height={theme.spacing(2.5)}
              variant="text"
            />
            <ContentSkeleton animate={animate} />
          </div>
          {displayHeaderAndNavigation && (
            <BaseRectSkeleton
              animate={animate}
              height={theme.spacing(footerHeight)}
            />
          )}
        </div>
      </div>
    </div>
  );
};

export default PageSkeleton;
