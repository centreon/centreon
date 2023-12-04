import { useParams } from 'react-router';

import { useFetchQuery } from '@centreon/ui';

import { playlistEndpoint } from '../../../api/endpoints';
import { Playlist } from '../models';
import { playlistDecoder } from '../api/decoders';

interface UseGetPlaylistState {
  playlist?: Playlist;
}

export const useGetPlaylist = (): UseGetPlaylistState => {
  const { dashboardId } = useParams();

  const { data: playlist } = useFetchQuery<Playlist>({
    baseEndpoint: 'http://localhost:5005/centreon/api/latest',
    decoder: playlistDecoder,
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
