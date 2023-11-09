import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  searchAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atom';
import { ResourceAccessRuleListingType } from '../models';

import { listingDecoder } from './api/decoders';
import { buildResourceAccessRulesEndpoint } from './api/endpoints';

interface LoadResourceAccessRules {
  data?: ResourceAccessRuleListingType;
  loading: boolean;
  refetch;
}

const useLoadResourceAccessRules = (): LoadResourceAccessRules => {
  const limit = useAtomValue(limitAtom);
  const page = useAtomValue(pageAtom);
  const searchValue = useAtomValue(searchAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const sortOrder = useAtomValue(sortOrderAtom);

  const search = {
    regex: {
      fields: ['rule', 'description'],
      value: searchValue
    }
  };
  const sort = { [sortField]: sortOrder };

  const {
    data,
    isLoading: loading,
    refetch
  } = useFetchQuery<ResourceAccessRuleListingType>({
    decoder: listingDecoder,
    getEndpoint: () => {
      return buildResourceAccessRulesEndpoint({
        limit: limit || 10,
        page: page || 1,
        search,
        sort
      });
    },
    getQueryKey: () => [
      'resource-access-rules',
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

export default useLoadResourceAccessRules;
