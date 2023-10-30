import { makeStyles } from 'tss-react/mui';

import { useTheme } from '@mui/material';

import BaseRectSkeleton from './BaseSkeleton';

import { PageSkeletonProps } from '.';

const numberOfActionButtons = 2;
const filterHeight = 6;
const paginationHeight = 4;
const actionBarHeight = 3.75;
const contentHeight = 40;

const useStyles = makeStyles()((theme) => ({
  actionBarPaginationContainer: {
    alignItems: 'center',
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(50)} ${theme.spacing(54)}`,
    justifyContent: 'space-between',
    marginLeft: theme.spacing(3),
    marginTop: theme.spacing(1.25)
  },
  actionBarSkeleton: {
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: `repeat(${numberOfActionButtons}, ${theme.spacing(
      10
    )})`
  },
  contentSkeleton: {
    marginLeft: theme.spacing(2),
    marginTop: theme.spacing(1)
  }
}));

const ContentSkeleton = ({
  animate
}: Pick<PageSkeletonProps, 'animate'>): JSX.Element => {
  const theme = useTheme();
  const { classes } = useStyles();

  return (
    <>
      <BaseRectSkeleton
        animate={animate}
        height={theme.spacing(filterHeight)}
      />
      <div className={classes.actionBarPaginationContainer}>
        <div className={classes.actionBarSkeleton}>
          {[...Array(numberOfActionButtons).keys()].map((id) => (
            <BaseRectSkeleton
              animate={animate}
              height={theme.spacing(actionBarHeight)}
              key={`base-bar-skeleton-${id}`}
            />
          ))}
        </div>
        <BaseRectSkeleton
          animate={animate}
          height={theme.spacing(paginationHeight)}
        />
      </div>
      <div className={classes.contentSkeleton}>
        <BaseRectSkeleton
          animate={animate}
          height={theme.spacing(contentHeight)}
        />
      </div>
    </>
  );
};

export default ContentSkeleton;
