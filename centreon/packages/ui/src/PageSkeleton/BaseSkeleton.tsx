import { makeStyles } from 'tss-react/mui';

import { SkeletonProps } from '@mui/material';

import LoadingSkeleton from '../LoadingSkeleton';

import { PageSkeletonProps } from '.';

export const useSkeletonStyles = makeStyles()((theme) => ({
  skeletonLayout: {
    borderRadius: theme.spacing(0.5)
  }
}));

const BaseRectSkeleton = ({
  animate,
  ...props
}: Pick<PageSkeletonProps, 'animate'> & SkeletonProps): JSX.Element => {
  const { classes } = useSkeletonStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
      className={classes.skeletonLayout}
      variant="rectangular"
      width="100%"
      {...props}
    />
  );
};

export default BaseRectSkeleton;
