import { useEffect, useState } from 'react';

import { equals, prop } from 'ramda';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import { Column, useFetchQuery } from '@centreon/ui';

import {
  resourceAccessManagementSearchAtom,
  selectedRowsAtom,
  resourceAccessRulesNamesAtom,
  editedResourceAccessRuleIdAtom,
  modalStateAtom
} from '../atom';
import {
  ResourceAccessRuleType,
  ResourceAccessRuleListingType,
  ModalMode
} from '../models';
import type { SortOrder } from '../models';
import { resourceAccessRuleDecoder } from '../AddEditResourceAccessRule/api/decoders';
import { resourceAccessRuleEndpoint } from '../AddEditResourceAccessRule/api/endpoints';

import { listingDecoder } from './api/decoders';
import { buildResourceAccessRulesEndpoint } from './api/endpoints';
import useListingColumns from './columns';

type UseListingState = {
  changePage: (page: number) => void;
  changeSort: ({
    sortField,
    sortOrder
  }: {
    sortField: string;
    sortOrder: SortOrder;
  }) => void;
  columns: Array<Column>;
  data?: ResourceAccessRuleListingType;
  loading: boolean;
  onRowClick: (row: ResourceAccessRuleType) => void;
  page: number | undefined;
  predefinedRowsSelection: Array<{
    label: string;
    rowCondition: (row: ResourceAccessRuleType) => boolean;
  }>;
  resetColumns: () => void;
  selectedColumnIds: Array<string>;
  selectedRows: Array<ResourceAccessRuleType>;
  setLimit: (limit: number | undefined) => void;
  setSelectedColumnIds: (selectedColumnIds: Array<string>) => void;
  setSelectedRows: (selectedRows: Array<ResourceAccessRuleType>) => void;
  sortF: string;
  sortO: SortOrder;
};

const useListing = (): UseListingState => {
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
  const [editRuleId, setEditedRuleId] = useAtom(editedResourceAccessRuleIdAtom);
  const searchValue = useAtomValue(resourceAccessManagementSearchAtom);
  const modalState = useAtomValue(modalStateAtom);
  const setResourceAccessRulesNames = useSetAtom(resourceAccessRulesNamesAtom);
  const setModalState = useSetAtom(modalStateAtom);

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

  const { fetchQuery } = useFetchQuery({
    decoder: resourceAccessRuleDecoder,
    getEndpoint: () => resourceAccessRuleEndpoint({ id: editRuleId }),
    getQueryKey: () => ['resource-access-rule', editRuleId],
    queryOptions: {
      enabled: equals(modalState.mode, ModalMode.Edit),
      suspense: false
    }
  });

  const onRowClick = (row: ResourceAccessRuleType): void => {
    setEditedRuleId(row.id);
    setModalState({ isOpen: true, mode: ModalMode.Edit });
    fetchQuery();
  };

  return {
    changePage,
    changeSort,
    columns,
    data,
    loading,
    onRowClick,
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
