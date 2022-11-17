import * as React from 'react';

import PageSkeleton from '.';

export default { title: 'Page Skeleton' };

export const normal = (): JSX.Element => <PageSkeleton animate={false} />;

export const normalWidthHeaderAndNavigation = (): JSX.Element => (
  <PageSkeleton displayHeaderAndNavigation animate={false} />
);
