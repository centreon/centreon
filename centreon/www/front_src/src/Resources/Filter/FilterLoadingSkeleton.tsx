<<<<<<< HEAD
import { Skeleton } from '@mui/material';
=======
import * as React from 'react';

import { Skeleton } from '@material-ui/lab';
>>>>>>> centreon/dev-21.10.x

const FilterLoadingSkeleton = (): JSX.Element => {
  return <Skeleton height={33} style={{ transform: 'none' }} width={175} />;
};

export default FilterLoadingSkeleton;
