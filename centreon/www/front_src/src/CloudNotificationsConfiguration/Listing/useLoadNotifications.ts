import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atom';
import { NotificationsListingType } from '../models';

import { listingDecoder } from './api/decoders';
import { buildNotificationsEndpoint } from './api/endpoints';

interface LoadNotifications {
  data?: NotificationsListingType;
  loading: boolean;
  refetch;
}

const useLoadingNotifications = (): LoadNotifications => {
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
  } = useFetchQuery<NotificationsListingType>({
    decoder: listingDecoder,
    getEndpoint: () => {
      return buildNotificationsEndpoint({
        limit: limit || 10,
        page: page || 1,
        search,
        sort
      });
    },
    getQueryKey: () => [
      'notifications',
      sortField,
      sortOrder,
      page,
      limit,
      search
    ],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  return { data, loading, refetch };
};

export default useLoadingNotifications;
