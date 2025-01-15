import { useEffect, useState } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { prop } from 'ramda';

import { MemoizedListing as Listing } from '@centreon/ui';

import { editedNotificationIdAtom, panelModeAtom } from '../Panel/atom';
import { PanelMode } from '../Panel/models';
import {
  isPanelOpenAtom,
  limitAtom,
  notificationsNamesAtom,
  pageAtom,
  panelWidthStorageAtom,
  selectedRowsAtom,
  sortFieldAtom,
  sortOrderAtom
} from '../atom';

import { Actions } from './Header';
import useListingColumns from './columns';
import useLoadingNotifications from './useLoadNotifications';

const NotificationsListing = (): JSX.Element => {
  const columns = useListingColumns();

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
  const [isPannelOpen, setIsPannelOpen] = useAtom(isPanelOpenAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const setLimit = useSetAtom(limitAtom);
  const setEditedNotificationId = useSetAtom(editedNotificationIdAtom);
  const setPanelMode = useSetAtom(panelModeAtom);
  const setNotificationsNames = useSetAtom(notificationsNamesAtom);
  const { loading, data: listingData, refetch } = useLoadingNotifications();

  useEffect(() => {
    refetch();
  }, []);

  useEffect(() => {
    if (listingData) {
      const names = listingData.result.map((item) => ({
        id: item.id,
        name: item.name
      }));
      setNotificationsNames(names);
    }
  }, [listingData]);

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const onRowClick = (row): void => {
    setEditedNotificationId(row.id);
    setPanelMode(PanelMode.Edit);
    setIsPannelOpen(true);
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

  return (
    <Listing
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
        isPannelOpen,
        predefinedRowsSelection,
        sorto,
        sortf,
        selectedRows
      ]}
      moveTablePagination={isPannelOpen}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={listingData?.result}
      selectedRows={selectedRows}
      sortField={sortf}
      sortOrder={sorto}
      totalRows={listingData?.meta.total}
      widthToMoveTablePagination={panelWidth}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={onRowClick}
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default NotificationsListing;
