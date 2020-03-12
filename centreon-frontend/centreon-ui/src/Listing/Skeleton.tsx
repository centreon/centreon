import React from 'react';

import { Skeleton } from '@material-ui/lab';

const ListingLoadingSkeleton = (): JSX.Element => (
  <>
    {['skeleton1', 'skeleton2', 'skeleton3'].map((key) => (
      <Skeleton key={key} height={20} animation="wave" />
    ))}
  </>
);

export default ListingLoadingSkeleton;
