import * as React from 'react';

import { Skeleton, SkeletonProps } from '@material-ui/lab';

const LoadingSkeleton = (props: SkeletonProps): JSX.Element => (
  <Skeleton animation="wave" variant="rect" {...props} />
);

export default LoadingSkeleton;
