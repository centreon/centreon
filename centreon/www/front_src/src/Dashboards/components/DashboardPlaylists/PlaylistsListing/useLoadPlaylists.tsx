import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom,
  searchAtom
} from './atom';
import { PlaylistListingType } from './models';
import { buildlistPlaylistsEndpoint, listPlaylistsDecoder } from './api';
import { formatListingData } from './utils';

interface UseLoadPlaylists {
  data?: PlaylistListingType;
  loading: boolean;
}

const useLoadPlaylists = (): UseLoadPlaylists => {
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const searchValue = useAtomValue(searchAtom);

  const sort = { [sortField]: sortOrder };
  const search = {
    regex: {
      fields: ['name'],
      value: searchValue
    }
  };

  const { data, isLoading: loading } = useFetchQuery<PlaylistListingType>({
    decoder: listPlaylistsDecoder,
    getEndpoint: () => {
      return buildlistPlaylistsEndpoint({
        limit: limit || 10,
        page: page || 1,
        search,
        sort
      });
    },
    getQueryKey: () => ['playlists', sortField, sortOrder, page, limit, search],
    queryOptions: {
      suspense: false
    }
  });

  return { data: formatListingData({ data }), loading };
};

export default useLoadPlaylists;
