import { useParams } from 'react-router';

import { useFetchQuery } from '@centreon/ui';

import { playlistEndpoint } from '../../../api/endpoints';
import { Playlist } from '../models';

interface UseGetPlaylistState {
  playlist?: Playlist;
}

export const useGetPlaylist = (): UseGetPlaylistState => {
  const { dashboardId } = useParams();

  const { data: playlist } = useFetchQuery<Playlist>({
    getEndpoint: () => playlistEndpoint(dashboardId as string),
    getQueryKey: () => ['playlist', dashboardId],
    queryOptions: {
      enabled: !!dashboardId,
      suspense: true
    }
  });

  return {
    playlist
  };
};
