import {
  QueryParameter,
  SearchParameter,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../atoms';

interface UseGetAllProps {
  sortField: string;
  sortOrder: string;
  page?: number;
  limit?: number;
  searchConditions: Array<SearchParameter>;
  filtersAtomKey: string;
  getCustomQueryParameters: () => Array<QueryParameter>;
}

const useGetAll = ({
  sortField,
  sortOrder,
  page,
  limit,
  searchConditions,
  filtersAtomKey,
  getCustomQueryParameters
}: UseGetAllProps) => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.getAll;
  const decoder = configuration?.api?.decoders?.getAll;
  const apiFormat = configuration?.api?.apiFormat;

  const sort = { [sortField]: sortOrder };

  const { data, isFetching } = useFetchQuery({
    decoder,
    getEndpoint: () =>
      buildListingEndpoint({
        apiFormat: apiFormat || 'Standard',
        baseEndpoint: endpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search: { conditions: searchConditions },
          sort
        },
        customQueryParameters: getCustomQueryParameters()
      }),
    getQueryKey: () => [
      'listResources',
      sortField,
      sortOrder,
      limit,
      page,
      configuration?.resourceType,
      filtersAtomKey
    ],
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetAll;
