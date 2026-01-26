import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

interface UseGetAllProps {
  baseEndpoint: string;
  decoder;
  limit?: number;
  page?: number;
  queryKey: Array<string>;
  searchConditions: Array<unknown>;
  sortField: string;
  sortOrder: string;
}

const useGetAll = ({
  sortField,
  sortOrder,
  page,
  limit,
  searchConditions,
  baseEndpoint,
  decoder,
  queryKey
}: UseGetAllProps) => {
  const sort = { [sortField]: sortOrder };

  const { data, isFetching } = useFetchQuery({
    decoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search: { conditions: searchConditions },
          sort
        }
      }),
    getQueryKey: () => queryKey,
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetAll;
