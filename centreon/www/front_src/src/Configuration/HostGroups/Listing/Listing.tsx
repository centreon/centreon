import { MemoizedListing } from '@centreon/ui';

import { useAtom } from 'jotai';
import { selectedRowsAtom } from '../atoms';
import ActionsBar from './ActionsBar/ActionsBar';
import useColumns from './Columns/columns';
import DeleteDialog from './Dialogs/DeleteDialog';
import DuplicateDialog from './Dialogs/DuplicateDialog';
import useListing from './useListing';

const Listing = (): JSX.Element => {
  const { columns } = useColumns();

  const {
    changePage,
    page,
    changeSort,
    resetColumns,
    setLimit,
    selectColumns,
    sortf,
    sorto,
    isLoading,
    data,
    selectedColumnIds
  } = useListing();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);

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
