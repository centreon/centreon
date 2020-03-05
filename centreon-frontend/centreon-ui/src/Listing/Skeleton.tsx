import React from 'react';

import { Skeleton } from '@material-ui/lab';

const ListingLoadingSkeleton = (): JSX.Element => (
  <>
    {new Array(3).fill(0).map(() => (
      <Skeleton height={20} animation="wave" />
    ))}
  </>
);

export default ListingLoadingSkeleton;
