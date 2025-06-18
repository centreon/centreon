import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';

interface UseGetAllProps {
  sortField: string;
  sortOrder: string;
  page?: number;
  limit?: number;
  searchConditions: Array<unknown>;
  baseEndpoint: string;
  decoder;
  queryKey: Array<string>;
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
