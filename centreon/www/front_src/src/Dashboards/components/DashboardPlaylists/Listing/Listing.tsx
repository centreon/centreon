import { useEffect, useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { map, prop, isEmpty, isNil } from 'ramda';

import { MemoizedListing } from '@centreon/ui';

import useListingColumns from './Columns/useColumns';
import useListing from './useListing';
import {
  pageAtom,
  limitAtom,
  sortOrderAtom,
  sortFieldAtom,
  selectedRowsAtom
} from './atom';
import { Actions } from './Actions';
import { formatShares } from './utils';
import { useIsViewerUser } from './hooks';

const Listing = (): JSX.Element => {
  const columns = useListingColumns();

  const { loading, data, refetch } = useListing();

  const result = map(
    (item) => {
      return {
        ...item,
        shares: formatShares({ shares: item.shares })
      };
    },
    data?.result || []
  );

  const listingData =
    isEmpty(data?.result) || isNil(data?.result) ? data : { ...data, result };

  const [selectedColumnIds, setSelectedColumnIds] = useState<Array<string>>(
    columns.map(prop('id'))
  );

  const resetColumns = (): void => {
    setSelectedColumnIds(columns.map(prop('id')));
  };

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);

  useEffect(() => {
    refetch();
  }, []);

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const predefinedRowsSelection = [
    {
      label: 'activated',
      rowCondition: (row): boolean => row?.isActivated
    },
    {
      label: 'deactivated',
      rowCondition: (row): boolean => !row?.isActivated
    }
  ];

  const isViewer = useIsViewerUser();

  return (
    <MemoizedListing
      actions={<Actions />}
      checkable={!isViewer}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      limit={listingData?.meta.limit}
      loading={loading}
      memoProps={[
        columns,
        page,
        predefinedRowsSelection,
        sorto,
        sortf,
        selectedRows
      ]}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={listingData?.result}
      selectedRows={selectedRows}
      sortField={sortf}
      sortOrder={sorto}
      subItems={{
        canCheckSubItems: false,
        enable: true,
        labelCollapse: 'Collapse',
        labelExpand: 'Expand',
        rowProperty: 'shares'
      }}
      totalRows={listingData?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={(): void => undefined}
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default Listing;
