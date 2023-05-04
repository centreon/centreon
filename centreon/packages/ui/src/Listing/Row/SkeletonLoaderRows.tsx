import React from 'react';

import LoadingSkeleton from '../../LoadingSkeleton';

import { EmptyRow } from './EmptyRow';

interface SkeletonLoaderProps {
  rows?: number;
}

const SkeletonLoader = ({ rows = 10 }: SkeletonLoaderProps): JSX.Element => (
  <>
    {[...Array(rows)].map((_v, i) => (
      <EmptyRow
        key={`skeleton-row-${i}`} // eslint-disable-line react/no-array-index-key
      >
        <LoadingSkeleton variant="text" width="100%" />
      </EmptyRow>
    ))}
  </>
);

export { SkeletonLoader };
