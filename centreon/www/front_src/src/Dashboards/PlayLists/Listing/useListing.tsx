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

interface UseListing {
  data?: PlaylistListingType;
  loading: boolean;
  refetch;
}

const useListing = (): UseListing => {
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

  const {
    data,
    isLoading: loading,
    refetch
  } = useFetchQuery<PlaylistListingType>({
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
      refetchOnMount: false,
      suspense: false
    }
  });

  return { data, loading, refetch };
};

export default useListing;
