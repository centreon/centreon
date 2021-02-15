import * as React from 'react';

import { useTheme, makeStyles } from '@material-ui/core';

import BaseRectSkeleton from './BaseSkeleton';

import { PageSkeletonProps } from '.';

const numberOfActionButtons = 2;
const filterHeight = 7.4;
const paginationHeight = 4;
const actionBarHeight = 3.75;
const contentHeight = 40;

const useStyles = makeStyles((theme) => ({
  actionBarPaginationContainer: {
    marginTop: theme.spacing(1.25),
    marginLeft: theme.spacing(3),
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(50)}px ${theme.spacing(54)}px`,
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  actionBarSkeleton: {
    display: 'grid',
    gridTemplateColumns: `repeat(${numberOfActionButtons}, ${theme.spacing(
      10,
    )}px)`,
    columnGap: `${theme.spacing(1)}px`,
  },
  contentSkeleton: {
    marginLeft: theme.spacing(2),
    marginTop: theme.spacing(1),
  },
}));

const ContentSkeleton = ({
  animate,
}: Pick<PageSkeletonProps, 'animate'>): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <>
      <BaseRectSkeleton
        height={theme.spacing(filterHeight)}
        animate={animate}
      />
      <div className={classes.actionBarPaginationContainer}>
        <div className={classes.actionBarSkeleton}>
          {Array(numberOfActionButtons)
            .fill(null)
            .map((_, idx) => (
              <BaseRectSkeleton
                key={idx.toString()}
                height={theme.spacing(actionBarHeight)}
                animate={animate}
              />
            ))}
        </div>
        <BaseRectSkeleton
          height={theme.spacing(paginationHeight)}
          animate={animate}
        />
      </div>
      <div className={classes.contentSkeleton}>
        <BaseRectSkeleton
          height={theme.spacing(contentHeight)}
          animate={animate}
        />
      </div>
    </>
  );
};

export default ContentSkeleton;
