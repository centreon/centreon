import {
  ListingModel,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../atoms';
import { HostGroupListItem } from '../models';
import { hostGroupsDecoderListDecoder } from './decoders';

interface UseLoadHostGroupsApiProps {
  sortField: string;
  sortOrder: string;
  page?: number;
  limit?: number;
  searchConditions: Array<unknown>;
}

const useLoadHostGroupsApi = ({
  sortField,
  sortOrder,
  page,
  limit,
  searchConditions
}: UseLoadHostGroupsApiProps) => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.endpoints?.getAll;

  const sort = { [sortField]: sortOrder };

  const { data, isFetching } = useFetchQuery<ListingModel<HostGroupListItem>>({
    decoder: hostGroupsDecoderListDecoder,
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

export default useLoadHostGroupsApi;
