import React from 'react';

import { Skeleton } from '@material-ui/lab';

const ListingLoadingSkeleton = (): JSX.Element => (
  <>
    {['skeleton1', 'skeleton2', 'skeleton3'].map((key) => (
      <Skeleton animation="wave" height={20} key={key} />
    ))}
  </>
);

export default ListingLoadingSkeleton;
