import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import { listTokensDecoder } from '../api/decoder';
import { buildListEndpoint, listTokensEndpoint } from '../api/endpoints';

import { currentFilterAtom } from './Actions/Search/Filter/atoms';
import { Fields, SortOrder } from './Actions/Search/Filter/models';
import { DataListing, SortParams, UseTokenListing } from './models';

export const useTokenListing = (): UseTokenListing => {
  const [dataListing, setDataListing] = useState<DataListing | undefined>();
  const [enabled, setEnabled] = useState(false);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);

  const getEndpoint = (): string => {
    return buildListEndpoint({
      endpoint: listTokensEndpoint,
      parameters: currentFilter
    });
  };

  const { data, isLoading, isError, refetch } = useFetchQuery({
    decoder: listTokensDecoder,
    getEndpoint,
    getQueryKey: () => ['listTokens', currentFilter],
    queryOptions: {
      enabled,
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
    setEnabled(true);
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
    onSort,
    refetch,
    sortOrder: Object.values(currentFilter?.sort)[0] as SortOrder,
    sortedField: Object.keys(currentFilter?.sort)[0] as Fields
  };
};
