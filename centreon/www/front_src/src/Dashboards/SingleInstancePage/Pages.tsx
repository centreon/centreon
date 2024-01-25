import { Suspense, lazy } from 'react';

import { PageSkeleton } from '@centreon/ui';

const Dashboard = lazy(() => import('./Dashboard/Dashboard'));

const Pages = (): JSX.Element => {
  return (
    <Suspense fallback={<PageSkeleton displayHeaderAndNavigation={false} />}>
      <Dashboard />
    </Suspense>
  );
};

export default Pages;
