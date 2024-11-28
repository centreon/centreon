import { useAtomValue } from 'jotai';

import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../components/DashboardLibrary/DashboardListing/atom';

import { dashboardListDecoder } from './decoders';
import { dashboardsEndpoint } from './endpoints';
import { List } from './meta.models';
import { Dashboard, resource } from './models';

type UseListDashboards = {
  data?: List<Omit<Dashboard, 'refresh'>>;
  isLoading: boolean;
};

const useListDashboards = (): UseListDashboards => {
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

  const { data, isLoading, isFetching } = useFetchQuery<
    List<Omit<Dashboard, 'refresh'>>
  >({
    decoder: dashboardListDecoder,
    doNotCancelCallsOnUnmount: true,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search,
          sort
        }
      }),
    getQueryKey: () => [
      resource.dashboards,
      sortField,
      sortOrder,
      page,
      limit,
      search
    ],
    queryOptions: {
      suspense: false
    }
  });

  return {
    data,
    isLoading: isLoading || isFetching
  };
};

export { useListDashboards };
