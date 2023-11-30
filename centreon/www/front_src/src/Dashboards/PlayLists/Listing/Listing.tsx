import { useEffect, useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { prop } from 'ramda';

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
import { listPlaylistsDecoder } from './api';
import fakeListingResponse from './Tests/fakeListingResponce.json';

const Listing = (): JSX.Element => {
  const columns = useListingColumns();

  const { loading, data: listingData, refetch } = useListing();

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

  //   const onRowClick = (row): void => {
  //     setEditedNotificationId(row.id);
  //     setPanelMode(PanelMode.Edit);
  //     setIsPannelOpen(true);
  //   };

  console.log(listingData?.result);

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

  return (
    <MemoizedListing
      checkable
      actions={<Actions />}
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
      //   moveTablePagination={isPannelOpen}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={listingData?.result}
      selectedRows={selectedRows}
      sortField={sortf}
      sortOrder={sorto}
      totalRows={listingData?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      //   onRowClick={onRowClick}
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default Listing;
