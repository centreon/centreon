import { useEffect, useState } from 'react';

import { prop } from 'ramda';
import { useSetAtom, useAtom } from 'jotai';

import { MemoizedListing } from '@centreon/ui';

import {
  limitAtom,
  pageAtom,
  resourceAccessRulesNamesAtom,
  selectedRowsAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atom';
import { ResourceAccessRuleType } from '../models';

import { useListingColumns } from './columns';
import useLoadResourceAccessRules from './useLoadResourceAccessRules';

const ResourceAccessRulesListing = (): JSX.Element => {
  const columns = useListingColumns();

  const [selectedColumnIds, setSelectedColumnIds] = useState(
    columns.map(prop('id'))
  );

  const resetColumns = (): void => {
    setSelectedColumnIds(columns.map(prop('id')));
  };

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const [sortO, setSortO] = useAtom(sortOrderAtom);
  const [sortF, setSortF] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setResourceAccessRulesNames = useSetAtom(resourceAccessRulesNamesAtom);
  const setLimit = useSetAtom(limitAtom);
  const { data: listingData, loading, refetch } = useLoadResourceAccessRules();

  useEffect(() => {
    refetch();
  }, []);

  useEffect(() => {
    if (listingData) {
      const names = listingData.result.map((item) => ({
        id: item.id,
        name: item.name
      }));
      setResourceAccessRulesNames(names);
    }
  }, [listingData]);

  const changeSort = ({ sortOrder, sortField }): void => {
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

  return (
    <MemoizedListing
      checkable
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      limit={listingData?.meta.limit}
      loading={loading}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={listingData?.result}
      selectedRows={selectedRows}
      sortField={sortF}
      sortOrder={sortO}
      totalRows={listingData?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default ResourceAccessRulesListing;
