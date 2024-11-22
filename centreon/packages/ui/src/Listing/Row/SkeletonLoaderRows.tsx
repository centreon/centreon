import LoadingSkeleton from '../../LoadingSkeleton';

import { EmptyRow } from './EmptyRow';

interface SkeletonLoaderProps {
  rows?: number;
}

const SkeletonLoader = ({ rows = 10 }: SkeletonLoaderProps): JSX.Element => (
  <>
    {[...Array(rows)]
      .map((_, i) => i)
      .map((v) => (
        <EmptyRow key={`skeleton-row-${v}`}>
          <LoadingSkeleton variant="text" width="100%" />
        </EmptyRow>
      ))}
  </>
);

export { SkeletonLoader };
