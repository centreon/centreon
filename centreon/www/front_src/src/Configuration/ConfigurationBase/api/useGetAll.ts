import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';

interface UseGetAllProps {
  sortField: string;
  sortOrder: string;
  page?: number;
  limit?: number;
  searchConditions: Array<unknown>;
}

const useGetAll = ({
  sortField,
  sortOrder,
  page,
  limit,
  searchConditions
}: UseGetAllProps) => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.getAll;
  const decoder = configuration?.api?.decoders?.getAll;

  const sort = { [sortField]: sortOrder };

  const { data, isFetching } = useFetchQuery({
    decoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: endpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search: { conditions: searchConditions },
          sort
        }
      }),
    getQueryKey: () => ['listHostGroups', sortField, sortOrder, limit, page],
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetAll;
