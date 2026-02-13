import { PageSkeleton } from '@centreon/ui';

import { lazy, Suspense } from 'react';
import { Route, Routes } from 'react-router';

import LegacyRoute from '../../route-components/legacyRoute';

const ReactRouter = lazy(() => import('../ReactRouter'));

const MainRouter = (): JSX.Element => (
  <Suspense fallback={<PageSkeleton />}>
    <Routes>
      <Route element={<LegacyRoute />} path="/main.php/*" />
      <Route element={<ReactRouter />} path="/*" />
    </Routes>
  </Suspense>
);

export default MainRouter;
