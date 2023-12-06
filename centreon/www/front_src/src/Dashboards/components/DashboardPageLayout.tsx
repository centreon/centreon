import { useMemo } from 'react';

import { always, cond, equals } from 'ramda';

import { DashboardLayout } from '../models';
import { routerHooks } from '../routerHooks';

import { DashboardsOverview } from './DashboardLibrary/DashboardsOverview/DashboardsOverview';
import DashboardPlaylistsOverview from './DashboardPlaylists/DashboardPlaylistsOverview';

const DashboardPageLayout = (): JSX.Element => {
  const { layout } = routerHooks.useParams();

  const Component = useMemo(
    () =>
      cond([
        [equals(DashboardLayout.Library), always(DashboardsOverview)],
        [equals(DashboardLayout.Playlist), always(DashboardPlaylistsOverview)]
      ])(layout as DashboardLayout),
    [layout]
  );

  return <Component />;
};

export default DashboardPageLayout;
