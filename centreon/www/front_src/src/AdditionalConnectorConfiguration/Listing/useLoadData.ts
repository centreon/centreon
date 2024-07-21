import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

import { additionalConnectorsEndpoint } from './api';
import { dashboardDecoderListDecoder } from './api/decoders';
import { AdditionalConnectors, List } from './models';

interface LoadDataProps {
  limit?: number;
  page: number | undefined;
  searchValue: string;
  sortField: string;
  sortOrder: 'asc' | 'desc';
}

interface LoadDataState {
  data?: List<AdditionalConnectors>;
  isLoading: boolean;
}

const useLoadData = ({
  page,
  limit,
  sortField,
  sortOrder,
  searchValue
}: LoadDataProps): LoadDataState => {
  const sort = { [sortField]: sortOrder };

  const search = {
    regex: {
      fields: ['name'],
      value: searchValue
    }
  };

  const { data, isLoading } = useFetchQuery({
    decoder: dashboardDecoderListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: additionalConnectorsEndpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search,
          sort
        }
      }),
    getQueryKey: () => [
      'listConnectors',
      searchValue,
      sortField,
      sortOrder,
      limit,
      page
    ],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  return { data, isLoading };
};

export default useLoadData;
