import { useMemo } from 'react';

import { useParams } from 'react-router-dom';
import { always, cond, equals } from 'ramda';

import { DashboardLayout } from '../models';

import { DashboardsOverview } from './DashboardLibrary/DashboardsOverview/DashboardsOverview';
import DashboardPlaylistsOverview from './DashboardPlaylists/DashboardPlaylistsOverview';

const DashboardPageLayout = (): JSX.Element => {
  const { layout } = useParams();

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
