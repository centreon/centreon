import { Column, MemoizedListing } from '@centreon/ui';

import { useAtom } from 'jotai';
import ActionsBar from './ActionsBar';
import useColumns from './Columns/useColumns';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import { selectedRowsAtom } from './atoms';
import useListing from './useListing';
import useLoadData from './useLoadData';

interface Props {
  columns: Array<Column>;
}

const Listing = ({ columns }: Props): JSX.Element => {
  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);

  const { staticColumns } = useColumns();

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
    openEditModal,
    rowColorConditions
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
        rowColorConditions={rowColorConditions}
        columns={[...columns, ...staticColumns]}
        currentPage={(page || 1) - 1}
        limit={data?.meta.limit}
        loading={isLoading}
        memoProps={[columns, staticColumns, page, sorto, sortf, selectedRows]}
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
