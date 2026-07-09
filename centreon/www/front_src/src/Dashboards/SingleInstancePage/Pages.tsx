import { PageSkeleton } from '@centreon/ui';

import { lazy, Suspense } from 'react';

const Dashboard = lazy(() => import('./Dashboard/Dashboard'));

const Pages = (): JSX.Element => {
  return (
    <Suspense fallback={<PageSkeleton displayHeaderAndNavigation={false} />}>
      <Dashboard />
    </Suspense>
  );
};

export default Pages;
