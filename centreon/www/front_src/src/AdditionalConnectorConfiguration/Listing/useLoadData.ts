import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import {
  ListingModel,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';

import { additionalConnectorsListDecoder } from '../api/decoders';
import { additionalConnectorsEndpoint } from '../api/endpoints';

import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atom';
import { AdditionalConnectorListItem, List } from './models';

interface LoadDataState {
  data?: List<AdditionalConnectorListItem>;
  isLoading: boolean;
  reload?;
}

const useLoadData = (): LoadDataState => {
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const filters = useAtomValue(filtersAtom);

  const sort = { [sortField]: sortOrder };

  const searchConditions = [
    ...(!filters.pollers
      ? []
      : filters.pollers.map((poller) => ({
          field: 'poller.name',
          values: {
            $rg: poller.name
          }
        }))),
    ...(!filters?.types
      ? []
      : filters.types.map((type) => ({
          field: 'type',
          values: {
            $rg: equals(type.name, 'VMWare_6/7') ? 'vmware_v6' : type.name
          }
        }))),
    ...(filters.name
      ? [
          {
            field: 'name',
            values: {
              $rg: filters.name
            }
          }
        ]
      : [])
  ];

  const { data, isFetching, fetchQuery } = useFetchQuery<
    ListingModel<AdditionalConnectorListItem>
  >({
    decoder: additionalConnectorsListDecoder,
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: additionalConnectorsEndpoint,
        parameters: {
          limit: limit || 10,
          page: page || 1,
          search: {
            conditions: searchConditions
          },
          sort
        }
      }),
    getQueryKey: () => ['listConnectors', sortField, sortOrder, limit, page],
    queryOptions: {
      refetchOnMount: false,
      staleTime: 0,
      suspense: false
    }
  });

  return { data, isLoading: isFetching, reload: fetchQuery };
};

export default useLoadData;
