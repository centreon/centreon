import { useMemo } from 'react';

import { always, cond, equals } from 'ramda';
import { useParams } from 'react-router';

import { DashboardLayout } from '../models';

import { Dashboard } from './Dashboard';
import { Playlist } from './Playlist';

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

  return <Component />;
};

export default Pages;
