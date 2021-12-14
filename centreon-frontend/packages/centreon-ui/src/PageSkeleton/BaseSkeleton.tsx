import * as React from 'react';

import { SkeletonProps } from '@material-ui/lab';
import { makeStyles } from '@material-ui/core';

import LoadingSkeleton from '../LoadingSkeleton';

import { PageSkeletonProps } from '.';

export const useSkeletonStyles = makeStyles((theme) => ({
  skeletonLayout: {
    borderRadius: theme.spacing(0.5),
  },
}));

const BaseRectSkeleton = ({
  animate,
  ...props
}: Pick<PageSkeletonProps, 'animate'> & SkeletonProps): JSX.Element => {
  const classes = useSkeletonStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
      className={classes.skeletonLayout}
      variant="rect"
      width="100%"
      {...props}
    />
  );
};

export default BaseRectSkeleton;
