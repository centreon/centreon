import { Skeleton, SkeletonProps } from '@mui/material';

const LoadingSkeleton = (props: SkeletonProps): JSX.Element => (
  <Skeleton animation="wave" variant="rectangular" {...props} />
);

export default LoadingSkeleton;
