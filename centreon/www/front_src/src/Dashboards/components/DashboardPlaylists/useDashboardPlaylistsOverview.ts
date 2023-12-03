import { isEmpty } from 'ramda';

import useLoadPlaylists from './PlaylistsListing/useLoadPlaylists';
import { PlaylistListingType } from './PlaylistsListing/models';

type UsePlaylistOverview = {
  data: PlaylistListingType;
  isEmptyList: boolean;
  loading: boolean;
};

const useDashboardPlaylistsOverview = (): UsePlaylistOverview => {
  const { loading, data } = useLoadPlaylists();

  const isEmptyList = isEmpty(data?.result || []);

  return {
    data: data as PlaylistListingType,
    isEmptyList,
    loading
  };
};

export { useDashboardPlaylistsOverview };
