import { MemoizedListing } from '@centreon/ui';
import Actions from './Actions';
import { useColumns } from './Columns/Columns';
import useListing from './useListing';
import useLoadData from './useLoadData';

const Listing = (): JSX.Element => {
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
    disableRowCondition
  } = useListing();

  return (
    <MemoizedListing
      actions={<Actions />}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      disableRowCondition={disableRowCondition}
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
      onSelectColumns={selectColumns}
      onSort={changeSort}
    />
  );
};

export default Listing;
