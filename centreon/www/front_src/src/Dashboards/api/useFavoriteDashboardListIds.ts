import { useSetAtom } from 'jotai';
import { useFetchQuery } from 'packages/ui/src';
import { useEffect } from 'react';
import { favoriteDashboardsIdsAtom } from '../components/DashboardLibrary/DashboardListing/Actions/favoriteAction/atoms';
import { favoriteDashboardListIdsDecoder } from './decoders';
import { dashboardsFavoriteEndpoit } from './endpoints';
import { FavoriteDashboardListIds } from './models';

const useFavoriteDashboardListIds = () => {
  const setFavoriteDashboardsIds = useSetAtom(favoriteDashboardsIdsAtom);
  const { data, isFetched } = useFetchQuery<FavoriteDashboardListIds>({
    decoder: favoriteDashboardListIdsDecoder,
    getEndpoint: () => dashboardsFavoriteEndpoit,
    getQueryKey: () => ['favoriteDashboardListIds'],
    queryOptions: {
      suspense: false
    }
  });

  useEffect(() => {
    if (!data) {
      return;
    }
    setFavoriteDashboardsIds(data);
  }, [data]);

  return isFetched;
};

export default useFavoriteDashboardListIds;
