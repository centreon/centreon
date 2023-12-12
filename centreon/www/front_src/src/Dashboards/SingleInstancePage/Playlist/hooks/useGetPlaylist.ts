import { useFetchQuery } from '@centreon/ui';

import { playlistEndpoint } from '../../../api/endpoints';
import { Playlist } from '../models';
import { playlistDecoder } from '../api/decoders';
import { router } from '../utils';

interface UseGetPlaylistState {
  playlist?: Playlist;
}

export const useGetPlaylist = (): UseGetPlaylistState => {
  const { dashboardId } = router.useParams();

  const { data: playlist } = useFetchQuery<Playlist>({
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
