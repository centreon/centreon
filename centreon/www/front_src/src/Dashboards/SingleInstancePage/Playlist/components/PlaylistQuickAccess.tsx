import { useSetAtom } from 'jotai';
import { generatePath, useNavigate, useParams } from 'react-router';
import { equals } from 'ramda';

import { PageLayout } from '@centreon/ui/components';

import { useListPlaylists } from '../hooks/useListPlaylists';
import { playlistConfigInitialValuesAtom } from '../../../components/DashboardPlaylists/atoms';
import { initialValue } from '../../../components/DashboardPlaylists/PlaylistConfig/utils';
import PlaylistConfig from '../../../components/DashboardPlaylists/PlaylistConfig/PlaylistConfig';
import { DashboardLayout } from '../../../models';
import {
  labelCreateAPlaylist,
  labelPlaylists
} from '../../../translatedLabels';

import routeMap from 'www/front_src/src/reactRoutes/routeMap';

const PlaylistQuickAccess = (): JSX.Element => {
  const { dashboardId } = useParams();
  const { playlists } = useListPlaylists();

  const navigate = useNavigate();

  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );

  const createPlaylist = (): void => {
    setPlaylistConfigInitialValues(initialValue);
  };

  const navigateToPlaylists = (): void => {
    navigate(
      generatePath(routeMap.dashboards, { layout: DashboardLayout.Playlist })
    );
  };

  const navigateToPlaylist = (id: string | number) => (): void => {
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId: id,
        layout: DashboardLayout.Playlist
      })
    );
  };

  return (
    <>
      <PageLayout.QuickAccess
        create={createPlaylist}
        elements={playlists}
        goBack={navigateToPlaylists}
        isActive={(id) => equals(id, dashboardId)}
        labels={{
          create: labelCreateAPlaylist,
          goBack: labelPlaylists
        }}
        navigateToElement={navigateToPlaylist}
      />
      <PlaylistConfig />
    </>
  );
};

export default PlaylistQuickAccess;
