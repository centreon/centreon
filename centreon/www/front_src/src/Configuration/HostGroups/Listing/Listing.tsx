import { MemoizedListing } from '@centreon/ui';

import { useAtom } from 'jotai';
import ActionsBar from './ActionsBar/ActionsBar';
import useColumns from './Columns/useColumns';
import { selectedRowsAtom } from './atoms';

import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useListing from './useListing';
import useLoadData from './useLoadData';

const Listing = (): JSX.Element => {
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
    selectedColumnIds
  } = useListing();

  return (
    <>
      <MemoizedListing
        checkable
        actions={<ActionsBar />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
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
        onRowClick={() => undefined}
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
