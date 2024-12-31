import { MemoizedListing } from '@centreon/ui';

import ActionsBar from './ActionsBar/ActionsBar';
import DeleteConnectorDialog from './Columns/Actions/DeleteDialog';
import useColumns from './Columns/columns';
import useListing from './useListing';

const Listing = (): JSX.Element => {
  const { columns } = useColumns();

  const {
    changePage,
    changeSort,
    page,
    resetColumns,
    setLimit,
    selectColumns,
    sortf,
    sorto,
    isLoading,
    data,
    selectedColumnIds,
    openEditDialog
  } = useListing();

  return (
    <>
      <MemoizedListing
        actions={<ActionsBar />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
        columns={columns}
        currentPage={(page || 1) - 1}
        limit={data?.meta.limit}
        loading={isLoading}
        memoProps={[columns, page, sorto, sortf]}
        rows={data?.result}
        sortField={sortf}
        sortOrder={sorto}
        totalRows={data?.meta.total}
        onLimitChange={setLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onRowClick={openEditDialog}
        onSelectColumns={selectColumns}
        onSort={changeSort}
      />
      <DeleteConnectorDialog />
    </>
  );
};

export default Listing;
