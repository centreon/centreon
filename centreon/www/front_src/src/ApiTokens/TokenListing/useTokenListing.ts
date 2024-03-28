import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { isNil } from 'ramda';

import { useFetchQuery } from '@centreon/ui';

import { listTokensDecoder } from '../api/decoder';
import { buildListEndpoint, listTokensEndpoint } from '../api/endpoints';

import { currentFilterAtom } from './Actions/Filter/atoms';
import { Fields, SortOrder } from './Actions/Filter/models';
import { DataListing, SortParams, UseTokenListing } from './models';

interface Props {
  enabled?: boolean;
}

export const useTokenListing = ({ enabled }: Props): UseTokenListing => {
  const [dataListing, setDataListing] = useState<DataListing | undefined>();
  const [defaultEnabled, setDefaultEnabled] = useState(false);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);

  const getEndpoint = (): string => {
    return buildListEndpoint({
      endpoint: listTokensEndpoint,
      parameters: currentFilter
    });
  };

  const { data, isLoading, isError, refetch, isRefetching } = useFetchQuery({
    decoder: listTokensDecoder,
    getEndpoint,
    getQueryKey: () => ['listTokens', currentFilter],
    queryOptions: {
      enabled: !isNil(enabled) ? enabled : defaultEnabled,
      suspense: false
    }
  });

  const changeLimit = (value: number): void => {
    setCurrentFilter((prev) => ({ ...prev, limit: Number(value) }));
  };

  const changePage = (value: number): void => {
    setCurrentFilter((prev) => ({ ...prev, page: value + 1 }));
  };

  const onSort = (sortParams: SortParams): void => {
    const { sortField, sortOrder } = sortParams;

    setCurrentFilter({
      ...currentFilter,
      sort: { [sortField]: sortOrder as SortOrder }
    });
  };
  useEffect(() => {
    setDefaultEnabled(true);
  }, []);

  useEffect(() => {
    if (!data) {
      setDataListing({ ...dataListing, isError, isLoading });

      return;
    }
    const {
      meta: { page, limit, total },
      result
    } = data;

    setDataListing({
      ...dataListing,
      isError,
      isLoading,
      limit,
      page,
      rows: result,
      total
    });
  }, [data]);

  return {
    changeLimit,
    changePage,
    dataListing: !dataListing?.rows
      ? (dataListing as DataListing)
      : ({
          ...dataListing,
          limit: currentFilter?.limit,
          page: currentFilter?.page
        } as DataListing),
    isRefetching,
    onSort,
    refetch,
    sortOrder: Object.values(currentFilter?.sort)[0] as SortOrder,
    sortedField: Object.keys(currentFilter?.sort)[0] as Fields
  };
};
