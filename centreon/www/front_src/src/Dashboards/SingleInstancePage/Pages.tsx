import { Suspense, lazy, useMemo } from 'react';

import { always, cond, equals } from 'ramda';
import { useParams } from 'react-router';

import { PageSkeleton } from '@centreon/ui';

import { DashboardLayout } from '../models';

const Dashboard = lazy(() => import('./Dashboard/Dashboard'));
const Playlist = lazy(() => import('./Playlist/Playlist'));

const Pages = (): JSX.Element => {
  const { layout } = useParams();

  const Component = useMemo(
    () =>
      cond([
        [equals(DashboardLayout.Library), always(Dashboard)],
        [equals(DashboardLayout.Playlist), always(Playlist)]
      ])(layout as DashboardLayout),
    [layout]
  );

  return (
    <Suspense fallback={<PageSkeleton displayHeaderAndNavigation={false} />}>
      <Component />
    </Suspense>
  );
};

export default Pages;
