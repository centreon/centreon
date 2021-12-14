import React from 'react';

import LoadingSkeleton from '.';

export default { title: 'Loading Skeleton' };

export const normal = (): JSX.Element => (
  <LoadingSkeleton height={50} width={400} />
);
