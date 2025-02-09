import { MemoizedListing } from '@centreon/ui';

import { useAtom } from 'jotai';
import ActionsBar from './ActionsBar/ActionsBar';
import useColumns from './Columns/useColumns';
import { selectedRowsAtom } from './atoms';

import { useTheme } from '@mui/material';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useListing from './useListing';
import useLoadData from './useLoadData';

const Listing = (): JSX.Element => {
  const theme = useTheme();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);

  const { columns } = useColumns();

  const { isLoading, data } = useLoadData();

  const {
    changePage,
    page,
    changeSort,
    resetColumns,
    setLimit,
    selectColumns,
    sortf,
    sorto,
    selectedColumnIds,
    openEditModal
  } = useListing();

  const rowColorConditions = [
    {
      color: theme.palette.action.disabledBackground,
      condition: ({ isActivated }): boolean => !isActivated,
      name: 'is_enabled'
    }
  ];

  return (
    <>
      <MemoizedListing
        checkable
        actions={<ActionsBar />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
        rowColorConditions={rowColorConditions}
        columns={columns}
        currentPage={(page || 1) - 1}
        limit={data?.meta.limit}
        loading={isLoading}
        memoProps={[columns, page, sorto, sortf, selectedRows]}
        rows={data?.result}
        sortField={sortf}
        sortOrder={sorto}
        totalRows={data?.meta.total}
        onLimitChange={setLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onRowClick={openEditModal}
        onSelectColumns={selectColumns}
        onSort={changeSort}
        selectedRows={selectedRows}
        onSelectRows={setSelectedRows}
      />
      <DeleteDialog />
      <DuplicateDialog />
    </>
  );
};

export default Listing;
