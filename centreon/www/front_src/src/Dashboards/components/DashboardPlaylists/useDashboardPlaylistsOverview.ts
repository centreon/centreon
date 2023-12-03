import { isEmpty } from 'ramda';

import useLoadListing from './PlaylistsListing/useLoadListing';
import { PlaylistListingType } from './PlaylistsListing/models';

type UsePlaylistOverview = {
  data: PlaylistListingType;
  isEmptyList: boolean;
  loading: boolean;
};

const useDashboardPlaylistsOverview = (): UsePlaylistOverview => {
  const { loading, data } = useLoadListing();

  const isEmptyList = isEmpty(data?.result || []);

  return {
    data: data as PlaylistListingType,
    isEmptyList,
    loading
  };
};

export { useDashboardPlaylistsOverview };
