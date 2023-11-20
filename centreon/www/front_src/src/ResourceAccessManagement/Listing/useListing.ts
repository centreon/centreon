import { useEffect, useState } from 'react';

import { prop } from 'ramda';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import {
  resourceAccessManagementSearchAtom,
  selectedRowsAtom,
  resourceAccessRulesNamesAtom
} from '../atom';
import {
  Listing,
  ResourceAccessRuleType,
  ResourceAccessRuleListingType
} from '../models';
import type { SortOrder } from '../models';

import { listingDecoder } from './api/decoders';
import { buildResourceAccessRulesEndpoint } from './api/endpoints';
import useListingColumns from './columns';

const useListing = (): Listing => {
  const columns = useListingColumns();
  const [selectedColumnIds, setSelectedColumnIds] = useState(
    columns.map(prop('id'))
  );
  const resetColumns = (): void => {
    setSelectedColumnIds(columns.map(prop('id')));
  };

  const [limit, setLimit] = useState<number | undefined>(10);
  const [page, setPage] = useState<number | undefined>(undefined);
  const [sortF, setSortF] = useState<string>('name');
  const [sortO, setSortO] = useState<SortOrder>('asc');
  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const searchValue = useAtomValue(resourceAccessManagementSearchAtom);
  const setResourceAccessRulesNames = useSetAtom(resourceAccessRulesNamesAtom);

  const sort = { [sortF]: sortO };
  const search = {
    regex: {
      fields: ['name', 'description'],
      value: searchValue
    }
  };

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
      sortF,
      sortO,
      page,
      limit,
      search
    ],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  useEffect(() => {
    refetch();
  }, []);

  useEffect(() => {
    if (data) {
      const names = data.result.map((item) => ({
        id: item.id,
        name: item.name
      }));
      setResourceAccessRulesNames(names);
    }
  }, [data]);

  const changeSort = ({
    sortField,
    sortOrder
  }: {
    sortField: string;
    sortOrder: SortOrder;
  }): void => {
    setSortF(sortField);
    setSortO(sortOrder);
  };

  const changePage = (updatedPage: number): void => {
    setPage(updatedPage + 1);
  };

  const predefinedRowsSelection = [
    {
      label: 'activated',
      rowCondition: (row: ResourceAccessRuleType): boolean => row.isActivated
    },
    {
      label: 'deactivated',
      rowCondition: (row: ResourceAccessRuleType): boolean => !row?.isActivated
    }
  ];

  return {
    changePage,
    changeSort,
    columns,
    data,
    loading,
    page,
    predefinedRowsSelection,
    resetColumns,
    selectedColumnIds,
    selectedRows,
    setLimit,
    setSelectedColumnIds,
    setSelectedRows,
    sortF,
    sortO
  };
};

export default useListing;
