import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom,
  searchAtom
} from '../atom';
import { NotificationsListingType } from '../models';

import { listingDecoder } from './api/decoders';
import { buildNotificationsEndpoint } from './api/endpoints';

interface LoadNotifications {
  data?: NotificationsListingType;
  loading: boolean;
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
      fields: ['name', 'resources', 'channels', 'users'],
      value: searchValue
    }
  };

  const { data, isLoading: loading } = useFetchQuery<NotificationsListingType>({
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
    isPaginated: true,
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  return { data, loading };
};

export default useLoadingNotifications;
